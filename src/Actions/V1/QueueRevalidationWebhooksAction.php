<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Data\V1\RevalidationWebhookData;
use Capell\Api\Jobs\DeliverRevalidationWebhookJob;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Facades\CapellCore;
use DateTimeInterface;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static void run(string $event, ?int $siteId, list<string> $paths = [], list<string> $keys = [], ?DateTimeInterface $at = null) */
final class QueueRevalidationWebhooksAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $keys
     */
    public function handle(string $event, ?int $siteId, array $paths = [], array $keys = [], ?DateTimeInterface $at = null): void
    {
        if (! CapellCore::isPackageEnabled(ApiServiceProvider::$packageName)) {
            return;
        }
        $endpoints = config('capell-api.webhooks.endpoints', []);
        if (! is_array($endpoints)) {
            return;
        }
        foreach ($endpoints as $key => $endpoint) {
            if (! is_string($key) || ! is_array($endpoint) || ! is_int($endpoint['site_id'] ?? null)
                || ($siteId !== null && $endpoint['site_id'] !== $siteId)) {
                continue;
            }
            $payload = new RevalidationWebhookData(
                id: (string) Str::uuid(),
                version: 'v1',
                event: $event,
                occurred_at: $at?->format(DATE_ATOM) ?? now()->toIso8601String(),
                paths: $paths,
                surrogate_keys: $keys === [] ? ['site-' . $endpoint['site_id']] : $keys,
            );
            $queue = config('capell-api.webhooks.queue', 'default');
            DeliverRevalidationWebhookJob::dispatch($key, $payload, $at?->getTimestamp())
                ->onQueue(is_string($queue) && $queue !== '' ? $queue : 'default')->delay($at === null ? 0 : min(900, max(0, $at->getTimestamp() - now()->getTimestamp())));
        }
    }
}
