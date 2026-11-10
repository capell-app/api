<?php

declare(strict_types=1);

namespace Capell\Api\Jobs;

use Capell\Api\Data\V1\RevalidationWebhookData;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Api\Support\WebhookSignature;
use Capell\Core\Facades\CapellCore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DeliverRevalidationWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public string $endpointKey, public RevalidationWebhookData $payload, public ?int $notBefore = null)
    {
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(): void
    {
        if (! CapellCore::isPackageEnabled(ApiServiceProvider::$packageName)) {
            return;
        }
        $endpoints = config('capell-api.webhooks.endpoints', []);
        $endpoint = is_array($endpoints) ? ($endpoints[$this->endpointKey] ?? null) : null;
        // Removing an endpoint revokes pending deliveries.
        if (! is_array($endpoint)) {
            return;
        }
        $url = $endpoint['url'] ?? null;
        $secret = $endpoint['secret'] ?? null;
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('API webhook endpoint configuration is invalid.');
        }
        if ($this->notBefore !== null && $this->notBefore > now()->getTimestamp()) {
            $configuredConnection = $this->connection ?? config('queue.default');
            $connection = is_string($configuredConnection) ? $configuredConnection : 'sync';
            if (config('queue.connections.' . $connection . '.driver') === 'sync') {
                throw new RuntimeException('Scheduled API revalidation requires an asynchronous queue.');
            }
            // Bound each hop for SQS and avoid spending delivery retry attempts on waiting.
            self::dispatch($this->endpointKey, $this->payload, $this->notBefore)
                ->onConnection($this->connection)->onQueue($this->queue)
                ->delay(min(900, $this->notBefore - now()->getTimestamp()));

            return;
        }
        $body = json_encode($this->payload->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        try {
            $response = Http::connectTimeout(3)->timeout(15)->withoutRedirecting()
                ->withHeaders([
                    'X-Capell-Timestamp' => (string) $timestamp,
                    'X-Capell-Signature' => WebhookSignature::sign($body, $timestamp, $secret),
                    'X-Capell-Delivery' => $this->payload->id,
                ])->withBody($body, 'application/json')->post($url);
            if (! $response->successful()) {
                throw new RuntimeException('API webhook delivery was not accepted.');
            }
        } catch (Throwable) {
            // Transport exceptions can contain endpoint credentials or response bodies.
            throw new RuntimeException('API webhook delivery failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('API webhook exhausted its retry policy.', ['delivery' => $this->payload->id]);
    }
}
