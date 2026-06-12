<?php

declare(strict_types=1);

use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Facades\CapellCore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('registers the api package metadata', function (): void {
    $package = CapellCore::getPackage(ApiServiceProvider::$packageName);

    expect($package->name)->toBe(ApiServiceProvider::$packageName)
        ->and(ApiServiceProvider::$name)->toBe('capell-api')
        ->and(ApiServiceProvider::$packageName)->toBe('capell-app/api');
});

it('loads host-configurable public api middleware defaults', function (): void {
    expect(config('capell-api.middleware'))->toBe(['api'])
        ->and(config('capell-api.public_pages.auth_middleware'))->toBeNull()
        ->and(config('capell-api.public_pages.rate_limit_middleware'))->toBe('throttle:capell-api')
        ->and(config('capell-api.public_pages.rate_limit_per_minute'))->toBe(60)
        ->and(config('capell-api.public_pages.max_candidate_sites'))->toBe(50)
        ->and(config('capell-api.public_pages.middleware'))->toBe([]);
});

it('applies the default public api throttle middleware to resolve routes', function (): void {
    expect(Route::getRoutes()->getByName('capell-api.pages.resolve')?->gatherMiddleware())
        ->toContain('throttle:capell-api')
        ->and(Route::getRoutes()->getByName('capell-api.v1.pages.resolve')?->gatherMiddleware())
        ->toContain('throttle:capell-api');
});

it('registers the documented capell api rate limiter', function (): void {
    $limiter = RateLimiter::limiter('capell-api');

    throw_unless($limiter instanceof Closure, RuntimeException::class, 'Expected API rate limiter to be registered.');

    $limits = $limiter(Request::create('/api/capell/v1/pages/resolve'));
    $limit = is_array($limits) ? $limits[0] : $limits;

    expect($limit)->toBeInstanceOf(Limit::class);
});
