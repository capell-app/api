<?php

declare(strict_types=1);

use Capell\Api\Http\Controllers\ResolvePageController;
use Capell\Api\Http\Controllers\V1PagesController;
use Capell\Api\Http\Middleware\ApiResponsePolicy;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Facades\CapellCore;
use Illuminate\Support\Facades\Route;

$configuredMiddleware = static function (mixed $middleware): array {
    if (in_array($middleware, [null, false, ''], true)) {
        return [];
    }

    if (is_string($middleware)) {
        return [$middleware];
    }

    if (! is_array($middleware)) {
        return [];
    }

    return array_values(array_filter(
        $middleware,
        static fn (mixed $middlewareName): bool => is_string($middlewareName) && $middlewareName !== '',
    ));
};

$apiMiddleware = [
    ApiResponsePolicy::class,
    ...$configuredMiddleware(config('capell-api.middleware', ['api'])),
    ...$configuredMiddleware(config('capell-api.public_pages.auth_middleware')),
    ...$configuredMiddleware(config('capell-api.public_pages.rate_limit_middleware', 'throttle:capell-api')),
    ...$configuredMiddleware(config('capell-api.public_pages.middleware', [])),
];

Route::middleware($apiMiddleware)
    ->prefix('api')
    ->group(function (): void {
        Route::get('capell/pages/resolve', ResolvePageController::class)
            ->name('capell-api.pages.resolve');

        Route::get('capell/v1/pages/resolve', ResolvePageController::class)
            ->name('capell-api.v1.pages.resolve');
    });

if (! CapellCore::isPackageEnabled(ApiServiceProvider::$packageName)) {
    return;
}

Route::middleware($apiMiddleware)->prefix('api/v1')->group(function (): void {
    Route::get('navigation', V1PagesController::class)->name('capell-api.v1.navigation');
    Route::get('media', V1PagesController::class)->name('capell-api.v1.media');
    Route::get('pages', V1PagesController::class)->name('capell-api.v1.pages');
    Route::get('pages/resolve', V1PagesController::class)->name('capell-api.v1.resolve');
    Route::get('collections/{type}', V1PagesController::class)->name('capell-api.v1.collections');
    Route::get('search', V1PagesController::class)->name('capell-api.v1.search');
});
