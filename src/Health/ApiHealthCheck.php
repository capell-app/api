<?php

declare(strict_types=1);

namespace Capell\Api\Health;

use Capell\Api\Http\Controllers\ResolvePageController;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiHealthCheck implements ChecksExtensionHealth
{
    private const string V1_ROUTE_NAME = 'capell-api.v1.pages.resolve';

    private const string V1_ROUTE_URI = 'api/capell/v1/pages/resolve';

    private const string API_VERSION_HEADER = 'X-Capell-Api-Version';

    private const string CACHE_TAG_HEADER = 'X-Capell-Cache-Tags';

    private const string API_VERSION = 'v1';

    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->v1RouteCheck(),
            $check->middlewareConfigurationCheck(),
            $check->responseHeadersCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function v1RouteCheck(): DoctorCheckResultData
    {
        $failures = $this->v1RouteFailures();

        return new DoctorCheckResultData(
            label: (string) __('capell-api::health.route.label'),
            passed: $failures === [],
            message: $failures === []
                ? (string) __('capell-api::health.route.passed')
                : (string) __('capell-api::health.route.failed', ['failures' => implode('; ', $failures)]),
            remediation: $failures === []
                ? null
                : (string) __('capell-api::health.route.remediation'),
        );
    }

    public function middlewareConfigurationCheck(): DoctorCheckResultData
    {
        $failures = $this->middlewareConfigurationFailures();

        return new DoctorCheckResultData(
            label: (string) __('capell-api::health.middleware.label'),
            passed: $failures === [],
            message: $failures === []
                ? (string) __('capell-api::health.middleware.passed')
                : (string) __('capell-api::health.middleware.failed', ['failures' => implode('; ', $failures)]),
            remediation: $failures === []
                ? null
                : (string) __('capell-api::health.middleware.remediation'),
        );
    }

    public function responseHeadersCheck(): DoctorCheckResultData
    {
        $headersPresent = $this->apiResponseHeadersPresent();

        return new DoctorCheckResultData(
            label: (string) __('capell-api::health.headers.label'),
            passed: $headersPresent,
            message: $headersPresent
                ? (string) __('capell-api::health.headers.passed')
                : (string) __('capell-api::health.headers.failed'),
            remediation: $headersPresent
                ? null
                : (string) __('capell-api::health.headers.remediation'),
        );
    }

    /**
     * @return list<string>
     */
    public function v1RouteFailures(): array
    {
        $failures = [];

        if (! $this->isPackageInstalled()) {
            $failures[] = (string) __('capell-api::health.route.failure.not_installed', [
                'package' => ApiServiceProvider::$packageName,
            ]);
        }

        $route = Route::getRoutes()->getByName(self::V1_ROUTE_NAME);

        if (! $route instanceof LaravelRoute) {
            $failures[] = (string) __('capell-api::health.route.failure.missing', [
                'route' => self::V1_ROUTE_NAME,
            ]);

            return $failures;
        }

        if (! in_array('GET', $route->methods(), true)) {
            $failures[] = (string) __('capell-api::health.route.failure.method');
        }

        if ($route->uri() !== self::V1_ROUTE_URI) {
            $failures[] = (string) __('capell-api::health.route.failure.uri', [
                'uri' => self::V1_ROUTE_URI,
            ]);
        }

        if (! $this->routeUsesResolveController($route)) {
            $failures[] = (string) __('capell-api::health.route.failure.controller', [
                'controller' => ResolvePageController::class,
            ]);
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    public function middlewareConfigurationFailures(): array
    {
        $failures = [];

        foreach ($this->middlewareConfigurationKeys() as $key) {
            if (! $this->hasValidMiddlewareConfiguration(config($key))) {
                $failures[] = (string) __('capell-api::health.middleware.failure.invalid_value', ['key' => $key]);
            }
        }

        $missingRouteMiddleware = $this->missingConfiguredRouteMiddleware();

        if ($missingRouteMiddleware !== []) {
            $failures[] = (string) __('capell-api::health.middleware.failure.missing_route_middleware', [
                'middleware' => implode(', ', $missingRouteMiddleware),
            ]);
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    public function missingConfiguredRouteMiddleware(): array
    {
        $route = Route::getRoutes()->getByName(self::V1_ROUTE_NAME);

        if (! $route instanceof LaravelRoute) {
            return [];
        }

        $routeMiddleware = $route->gatherMiddleware();

        return array_values(array_diff($this->configuredRouteMiddleware(), $routeMiddleware));
    }

    public function apiResponseHeadersPresent(): bool
    {
        if ($this->v1RouteFailures() !== []) {
            return false;
        }

        try {
            $request = Request::create('/' . self::V1_ROUTE_URI, 'GET', [
                'url' => '/__capell_api_health_check__',
            ]);
            $request->headers->set('Host', 'capell-api-health.test');

            $response = $this->dispatchHealthRequestWithoutMiddleware($request);
            $cacheTags = $response->headers->get(self::CACHE_TAG_HEADER);

            return $response->headers->get(self::API_VERSION_HEADER) === self::API_VERSION
                && is_string($cacheTags)
                && in_array('api', array_filter(explode(',', $cacheTags)), true);
        } catch (Throwable) {
            return false;
        }
    }

    public function isPackageInstalled(): bool
    {
        try {
            return CapellCore::isPackageInstalled(ApiServiceProvider::$packageName);
        } catch (Throwable) {
            return false;
        }
    }

    private function dispatchHealthRequestWithoutMiddleware(Request $request): Response
    {
        $container = app();
        $middlewareDisableWasBound = $container->bound('middleware.disable');
        $previousMiddlewareDisable = $middlewareDisableWasBound
            ? $container->make('middleware.disable')
            : null;

        $container->instance('middleware.disable', true);

        try {
            return Route::dispatch($request);
        } finally {
            if ($middlewareDisableWasBound) {
                $container->instance('middleware.disable', $previousMiddlewareDisable);
            } else {
                $container->forgetInstance('middleware.disable');
            }
        }
    }

    private function routeUsesResolveController(LaravelRoute $route): bool
    {
        $actionName = $route->getActionName();

        return $actionName === ResolvePageController::class
            || str_starts_with($actionName, ResolvePageController::class . '@');
    }

    /**
     * @return list<string>
     */
    private function middlewareConfigurationKeys(): array
    {
        return [
            'capell-api.middleware',
            'capell-api.public_pages.auth_middleware',
            'capell-api.public_pages.rate_limit_middleware',
            'capell-api.public_pages.middleware',
        ];
    }

    private function hasValidMiddlewareConfiguration(mixed $middleware): bool
    {
        if (in_array($middleware, [null, false, ''], true)) {
            return true;
        }

        if (is_string($middleware)) {
            return true;
        }

        if (! is_array($middleware)) {
            return false;
        }

        return collect($middleware)
            ->every(static fn (mixed $middlewareName): bool => is_string($middlewareName) && $middlewareName !== '');
    }

    /**
     * @return list<string>
     */
    private function configuredRouteMiddleware(): array
    {
        return array_values(collect($this->middlewareConfigurationKeys())
            ->flatMap(fn (string $key): array => $this->configuredMiddleware(config($key)))
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @return list<string>
     */
    private function configuredMiddleware(mixed $middleware): array
    {
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
    }
}
