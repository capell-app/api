<?php

declare(strict_types=1);

namespace Capell\Api\Providers;

use Capell\Api\Actions\V1\QueueRevalidationWebhooksAction;
use Capell\Api\Listeners\QueuePageRevalidation;
use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Events\PageUrlChanged;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\PublicRead\PublicReadWithdrawalSteps;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Override;
use ReflectionProperty;
use Spatie\LaravelPackageTools\Package;

final class ApiServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-api';

    public static string $packageName = 'capell-app/api';

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations();

        if (file_exists(__DIR__ . '/../../routes/api.php')) {
            $package->hasRoute('api');
        }
    }

    #[Override]
    public function packageBooted(): void
    {
        if (! $this->isPackageInstalled()) {
            $this->registerRateLimiter();
        }
    }

    #[Override]
    protected function bootPackageRoutes(): self
    {
        if (! $this->isPackageInstalled()) {
            $this->registerRoutes();
        }

        return $this;
    }

    #[Override]
    protected function bootInstalledRuntime(): void
    {
        $this->registerRoutes();
        $this->registerRateLimiter();
        $this->registerRevalidation();
    }

    private function registerRevalidation(): void
    {
        Event::listen(PageUrlChanged::class, static function (PageUrlChanged $event): void {
            QueueRevalidationWebhooksAction::run('page.url_changed', $event->site_id);
        });
        Event::listen(PageSaved::class, [QueuePageRevalidation::class, 'handle']);
        Event::listen(PageDeleted::class, [QueuePageRevalidation::class, 'handle']);
        $this->app->make(PublicReadWithdrawalSteps::class)->register('api.headless', static function (?int $siteId): void {
            QueueRevalidationWebhooksAction::run('site.eligibility_changed', $siteId);
        });
    }

    private function registerRoutes(): void
    {
        $router = $this->app->make(Router::class);
        $groups = new ReflectionProperty(Router::class, 'groupStack');
        $previous = $groups->getValue($router);
        $groups->setValue($router, []);
        try {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        } finally {
            $groups->setValue($router, $previous);
        }
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('capell-api', function (Request $request): Limit {
            $maxAttempts = config('capell-api.public_pages.rate_limit_per_minute', 60);
            $attempts = is_int($maxAttempts) ? $maxAttempts : 60;

            return Limit::perMinute($attempts)
                ->by((string) $request->ip());
        });
    }
}
