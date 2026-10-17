<?php

declare(strict_types=1);

namespace Capell\Api\Providers;

use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
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
