<?php

declare(strict_types=1);

namespace Capell\Api\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

final class WebhookSignature
{
    public static function sign(string $body, int $timestamp, string $secret): string
    {
        return 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * Authenticate exact bytes and process once. Callback failures remain retryable.
     * The callback must finish within the 60-second lease; use a shared lock-capable store.
     *
     * @param  Closure(array<string, mixed>): void  $process
     */
    public static function process(string $body, string $timestamp, string $signature, string $secret, Repository $replayCache, Closure $process, ?int $now = null): WebhookProcessingResult
    {
        if ($secret === '' || strlen($body) > 1048576 || ! ctype_digit($timestamp) || strlen($timestamp) > 11
            || abs(($now ?? time()) - (int) $timestamp) > 300
            || ! hash_equals(self::sign($body, (int) $timestamp, $secret), $signature)) {
            return WebhookProcessingResult::Invalid;
        }
        $payload = json_decode($body, true, 16);
        $id = is_array($payload) ? ($payload['id'] ?? null) : null;
        if (! is_string($id) || preg_match('/^[0-9a-f-]{36}$/D', $id) !== 1) {
            return WebhookProcessingResult::Invalid;
        }
        foreach (array_keys($payload) as $field) {
            if (! is_string($field)) {
                return WebhookProcessingResult::Invalid;
            }
        }
        /** @var array<string, mixed> $payload */
        $store = $replayCache->getStore();
        if (! $store instanceof LockProvider) {
            throw new RuntimeException('Webhook processing requires an atomic lock-capable cache.');
        }
        $key = 'capell-api:webhook:' . hash('sha256', $secret . ':' . $id);
        $lock = $store->lock($key . ':processing', 60);
        if (! $lock->get()) {
            return WebhookProcessingResult::Busy;
        }
        try {
            if ($replayCache->has($key)) {
                return WebhookProcessingResult::Duplicate;
            }
            $process($payload);
            if (! $replayCache->put($key, true, 86400)) {
                throw new RuntimeException('Webhook completion could not be recorded.');
            }

            return WebhookProcessingResult::Processed;
        } finally {
            $lock->release();
        }
    }
}
