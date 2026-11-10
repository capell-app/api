<?php

declare(strict_types=1);

use Capell\Api\Actions\V1\QueueRevalidationWebhooksAction;
use Capell\Api\Data\V1\RevalidationWebhookData;
use Capell\Api\Health\ApiHealthCheck;
use Capell\Api\Jobs\DeliverRevalidationWebhookJob;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Api\Support\WebhookProcessingResult;
use Capell\Api\Support\WebhookSignature;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\PublicRead\PublicReadWithdrawalSteps;
use Capell\Core\Support\Publishing\PublishSentinel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;

it('authenticates exact webhook bytes and rejects tampering expiry and replay', function (): void {
    $secret = str_repeat('a', 32);
    $body = json_encode(['id' => 'c93a0d92-e6e3-4115-85f9-d180fb8e80bd', 'event' => 'page.published'], JSON_THROW_ON_ERROR);
    $now = now()->getTimestamp();
    $signature = WebhookSignature::sign($body, $now, $secret);
    $cache = Cache::store('array');
    $process = static function (array $payload): void {};
    expect(WebhookSignature::process($body . ' ', (string) $now, $signature, $secret, $cache, $process, $now))->toBe(WebhookProcessingResult::Invalid);
    expect(WebhookSignature::process($body, (string) ($now - 301), WebhookSignature::sign($body, $now - 301, $secret), $secret, $cache, $process, $now))->toBe(WebhookProcessingResult::Invalid);
    expect(WebhookSignature::process($body, (string) $now, $signature, $secret, $cache, $process, $now))->toBe(WebhookProcessingResult::Processed);
    expect(WebhookSignature::process($body, (string) $now, $signature, $secret, $cache, $process, $now))->toBe(WebhookProcessingResult::Duplicate);
});

it('queues scoped notifications without secrets and signs the delivered bytes', function (): void {
    $secret = str_repeat('s', 32);
    config()->set('capell-api.webhooks.endpoints', [
        'agency' => ['site_id' => 10, 'url' => 'https://agency.example/revalidate', 'secret' => $secret],
        'other' => ['site_id' => 11, 'url' => 'https://other.example/revalidate', 'secret' => str_repeat('x', 32)],
    ]);
    Queue::fake();
    QueueRevalidationWebhooksAction::run('page.published', 10);
    Queue::assertPushed(DeliverRevalidationWebhookJob::class, 1);
    $queue = Queue::getFacadeRoot();
    throw_unless($queue instanceof QueueFake, LogicException::class);
    $job = $queue->pushed(DeliverRevalidationWebhookJob::class)->first();
    throw_unless($job instanceof DeliverRevalidationWebhookJob, LogicException::class);
    expect(serialize($job))->not->toContain($secret, 'agency.example');
    Http::fake(['agency.example/*' => Http::response('', 204)]);
    $job->handle();
    Http::assertSent(function (Request $request) use ($secret): bool {
        $timestamp = $request->header('X-Capell-Timestamp')[0];

        return $request->header('X-Capell-Signature')[0] === WebhookSignature::sign($request->body(), (int) $timestamp, $secret)
            && $request->data()['event'] === 'page.published';
    });
    expect($job->tries)->toBe(5)->and($job->backoff())->toBe([10, 60, 300, 900]);
});

it('fails unsuccessful deliveries with redacted errors and honours endpoint revocation', function (): void {
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => 1, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    $job = new DeliverRevalidationWebhookJob('agency', new RevalidationWebhookData('c93a0d92-e6e3-4115-85f9-d180fb8e80bd', 'v1', 'page.deleted', now()->toIso8601String(), [], ['site-1']));
    Http::fake(['agency.example/*' => Http::response('secret response body', 500)]);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'API webhook delivery failed.');
    config()->set('capell-api.webhooks.endpoints', []);
    $job->handle();
    Http::assertSentCount(1);
});

it('does not consume a delivery when the receiver callback fails', function (): void {
    $secret = str_repeat('r', 32);
    $body = json_encode(['id' => 'c93a0d92-e6e3-4115-85f9-d180fb8e80be'], JSON_THROW_ON_ERROR);
    $time = now()->getTimestamp();
    $signature = WebhookSignature::sign($body, $time, $secret);
    $cache = Cache::store('array');
    expect(fn () => WebhookSignature::process($body, (string) $time, $signature, $secret, $cache, static function (array $payload): void {
        throw new RuntimeException('Temporary purge failure');
    }, $time))->toThrow(RuntimeException::class, 'Temporary purge failure');
    $calls = 0;
    $process = static function (array $payload) use (&$calls): void {
        $calls++;
    };
    expect(WebhookSignature::process($body, (string) $time, $signature, $secret, $cache, $process, $time))->toBe(WebhookProcessingResult::Processed);
    expect(WebhookSignature::process($body, (string) $time, $signature, $secret, $cache, $process, $time))->toBe(WebhookProcessingResult::Duplicate);
    expect($calls)->toBe(1);
});

it('reports configured webhooks using the sync driver as unhealthy', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => 1, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    expect((new ApiHealthCheck)->webhookConfigurationCheck()->passed)->toBeFalse();
});

it('queues date transitions with bounded delays and keeps secrets outside the job', function (): void {
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => 1, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    Queue::fake();
    $at = now()->addDay();
    QueueRevalidationWebhooksAction::run('page.unpublished', 1, at: $at);
    Queue::assertPushed(DeliverRevalidationWebhookJob::class, static fn (DeliverRevalidationWebhookJob $job): bool => $job->notBefore === $at->getTimestamp() && $job->delay === 900);
});

it('registers policy withdrawals for every configured endpoint site', function (): void {
    config()->set('capell-api.webhooks.endpoints', [
        'one' => ['site_id' => 1, 'url' => 'https://one.example/revalidate', 'secret' => str_repeat('s', 32)],
        'two' => ['site_id' => 2, 'url' => 'https://two.example/revalidate', 'secret' => str_repeat('t', 32)],
    ]);
    Queue::fake();
    $step = app(PublicReadWithdrawalSteps::class)->get('api.headless');
    expect($step)->toBeInstanceOf(Closure::class);
    throw_unless($step instanceof Closure, LogicException::class);
    $step(null);
    Queue::assertPushed(DeliverRevalidationWebhookJob::class, 2);
    Queue::assertPushed(DeliverRevalidationWebhookJob::class, static fn (DeliverRevalidationWebhookJob $job): bool => $job->payload->event === 'site.eligibility_changed' && $job->payload->surrogate_keys === ['site-2']);
});

it('revokes queued webhook delivery when the API package is disabled', function (): void {
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => 1, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    $job = new DeliverRevalidationWebhookJob('agency', new RevalidationWebhookData('c93a0d92-e6e3-4115-85f9-d180fb8e80bd', 'v1', 'page.deleted', now()->toIso8601String(), [], ['site-1']));
    Queue::fake();
    Http::fake();
    CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName, false);
    try {
        QueueRevalidationWebhooksAction::run('page.deleted', 1);
        $job->handle();
        Queue::assertNotPushed(DeliverRevalidationWebhookJob::class);
        Http::assertNothingSent();
    } finally {
        CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName);
    }
});

it('queues real page lifecycle transitions only after commit', function (string $transition): void {
    $this->freezeTime();
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    $page = Page::factory()->site($site)->withTranslations($language)->create();
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => $site->id, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    Queue::fake();
    $connection = $page->getConnection();
    $connection->beginTransaction();
    match ($transition) {
        'publish' => $page->update(['visible_from' => now()->subMinute(), 'visible_until' => null]),
        'draft' => $page->update(['visible_from' => PublishSentinel::draftValue()]),
        'expire' => $page->update(['visible_until' => now()->subMinute()]),
        'schedule' => $page->update(['visible_from' => now()->addDay(), 'visible_until' => now()->addDays(2)]),
        'delete' => $page->delete(),
        default => throw new InvalidArgumentException('Unknown lifecycle transition'),
    };
    Queue::assertNotPushed(DeliverRevalidationWebhookJob::class);
    $connection->commit();
    $event = match ($transition) {
        'publish' => 'page.published',
        'delete' => 'page.deleted',
        default => 'page.unpublished',
    };
    Queue::assertPushed(DeliverRevalidationWebhookJob::class, static fn (DeliverRevalidationWebhookJob $job): bool => $job->payload->event === $event
        && $job->notBefore === null && $job->payload->surrogate_keys === ['site-' . $site->id, 'page-' . $page->id]);
    if ($transition === 'schedule') {
        Queue::assertPushed(DeliverRevalidationWebhookJob::class, static fn (DeliverRevalidationWebhookJob $job): bool => $job->payload->event === 'page.published' && $job->notBefore === now()->addDay()->getTimestamp() && $job->delay === 900);
        Queue::assertPushed(DeliverRevalidationWebhookJob::class, static fn (DeliverRevalidationWebhookJob $job): bool => $job->payload->event === 'page.unpublished' && $job->notBefore === now()->addDays(2)->getTimestamp() && $job->delay === 900);
    } else {
        Queue::assertPushed(DeliverRevalidationWebhookJob::class, 1);
    }
})->with(['publish', 'draft', 'expire', 'schedule', 'delete']);

it('discards page revalidation when the authoring transaction rolls back', function (): void {
    $this->freezeTime();
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    $page = Page::factory()->site($site)->withTranslations($language)->create();
    config()->set('capell-api.webhooks.endpoints.agency', ['site_id' => $site->id, 'url' => 'https://agency.example/revalidate', 'secret' => str_repeat('s', 32)]);
    Queue::fake();
    $connection = $page->getConnection();
    $connection->beginTransaction();
    $page->update(['visible_from' => PublishSentinel::draftValue()]);
    $connection->rollBack();
    Queue::assertNotPushed(DeliverRevalidationWebhookJob::class);
});
