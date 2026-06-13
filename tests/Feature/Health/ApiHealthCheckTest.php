<?php

declare(strict_types=1);

use Capell\Api\Health\ApiHealthCheck;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

it('reports a compatible capell api version', function (): void {
    expect(ApiHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('runs real api diagnostics and passes when the route contract is healthy', function (): void {
    $results = ApiHealthCheck::runDiagnostics();
    $check = new ApiHealthCheck;

    expect($results)->toHaveCount(3)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue()
        ->and(ApiHealthCheck::passed())->toBeTrue()
        ->and($check->v1RouteFailures())->toBe([])
        ->and($check->middlewareConfigurationFailures())->toBe([])
        ->and($check->apiResponseHeadersPresent())->toBeTrue()
        ->and($results->pluck('label')->all())->toBe([
            __('capell-api::health.route.label'),
            __('capell-api::health.middleware.label'),
            __('capell-api::health.headers.label'),
        ]);
});

it('fails the route diagnostic when the api package is not installed', function (): void {
    CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName, false);

    try {
        $check = new ApiHealthCheck;
        $result = $check->v1RouteCheck();

        expect($check->isPackageInstalled())->toBeFalse()
            ->and($check->v1RouteFailures())->not->toBe([])
            ->and($result->passed)->toBeFalse()
            ->and(ApiHealthCheck::passed())->toBeFalse();
    } finally {
        CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName);
    }
});

it('fails the middleware diagnostic when host middleware config is not publishable', function (): void {
    Config::set('capell-api.public_pages.middleware', ['auth:sanctum', 123]);

    $check = new ApiHealthCheck;
    $result = $check->middlewareConfigurationCheck();

    expect($check->middlewareConfigurationFailures())->toContain(
        __('capell-api::health.middleware.failure.invalid_value', ['key' => 'capell-api.public_pages.middleware']),
    )
        ->and($result->passed)->toBeFalse()
        ->and(ApiHealthCheck::passed())->toBeFalse();
});

it('fails the middleware diagnostic when configured middleware is missing from the route', function (): void {
    Config::set('capell-api.public_pages.auth_middleware', 'auth:sanctum');

    $check = new ApiHealthCheck;
    $result = $check->middlewareConfigurationCheck();

    expect($check->missingConfiguredRouteMiddleware())->toBe(['auth:sanctum'])
        ->and($result->passed)->toBeFalse()
        ->and($result->message)->toContain('auth:sanctum')
        ->and(ApiHealthCheck::passed())->toBeFalse();
});

it('fails the header diagnostic when the resolve route stops returning api contract headers', function (): void {
    $routes = clone Route::getRoutes();

    try {
        Route::get('api/capell/v1/pages/resolve', static fn (): array => ['ok' => true]);

        $check = new ApiHealthCheck;
        $result = $check->responseHeadersCheck();

        expect($check->apiResponseHeadersPresent())->toBeFalse()
            ->and($result->passed)->toBeFalse()
            ->and(ApiHealthCheck::passed())->toBeFalse();
    } finally {
        if ($routes instanceof RouteCollection) {
            app('router')->setRoutes($routes);
            app('url')->setRoutes($routes);
        }
    }
});
