<?php

declare(strict_types=1);

use Capell\Api\Health\ApiHealthCheck;
use Capell\Api\Manifest\ApiRoutesContribution;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Capell\Core\Support\Manifest\ManifestValidator;

it('declares shipped api manifest surfaces without duplicate health classes', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $composer = capell_json_file_array($packagePath . '/composer.json');
    $contributions = data_get($manifest, 'contributes');

    throw_unless(is_array($contributions), RuntimeException::class, 'Expected API manifest contributions.');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/api', $packagePath . '/capell.json');

    $routeContribution = collect($contributions)
        ->first(static fn (mixed $contribution): bool => is_array($contribution) && ($contribution['type'] ?? null) === 'route');

    throw_unless(is_array($routeContribution), RuntimeException::class, 'Expected API route contribution.');

    $apiEndpoints = $routeContribution['apiEndpoints'] ?? null;

    throw_unless(is_array($apiEndpoints), RuntimeException::class, 'Expected API endpoint contribution metadata.');

    $healthChecks = data_get($manifest, 'healthChecks', []);

    throw_unless(is_array($healthChecks), RuntimeException::class, 'Expected API manifest health checks.');

    $healthClasses = collect($healthChecks)
        ->pluck('class')
        ->filter()
        ->values()
        ->all();

    expect($manifest)
        ->toHaveKey('manifest-version', 3)
        ->toHaveKey('name', 'capell-app/api')
        ->toHaveKey('namespace', 'Capell\\Api')
        ->and(data_get($manifest, 'security.publicSurface.routeNames'))->toBe([
            'capell-api.pages.resolve',
            'capell-api.v1.pages.resolve',
        ])
        ->and(data_get($manifest, 'security.publicSurface.auth'))->toBe('public')
        ->and(data_get($manifest, 'security.publicOutput.cacheSafe'))->toBeTrue()
        ->and(data_get($manifest, 'security.publicOutput.forbidAuthoringSurface'))->toBeTrue()
        ->and(data_get($manifest, 'security.publicOutput.forbidSecrets'))->toBeTrue()
        ->and($healthClasses)->toBe([ApiHealthCheck::class])
        ->and(data_get($manifest, 'healthChecks.0.key'))->toBe('api.package-health')
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->toBe([]);

    expect($routeContribution)->toBe([
        'type' => 'route',
        'class' => ApiRoutesContribution::class,
        'routes' => [
            'capell-api.pages.resolve',
            'capell-api.v1.pages.resolve',
        ],
        'prefix' => 'api',
        'middleware' => [
            'api',
            'throttle:capell-api',
        ],
        'apiEndpoints' => [
            [
                'type' => 'public-api-endpoint',
                'name' => 'public-page-resolve-v1',
                'routeName' => 'capell-api.v1.pages.resolve',
                'method' => 'GET',
                'path' => '/api/capell/v1/pages/resolve',
                'version' => 'v1',
                'auth' => 'public',
                'readOnly' => true,
                'canonical' => true,
                'signedContextParameters' => ['site', 'language'],
                'publicSafety' => ['sanitized-html', 'no-authoring-surface', 'no-secrets'],
            ],
            [
                'type' => 'public-api-endpoint',
                'name' => 'public-page-resolve-legacy',
                'routeName' => 'capell-api.pages.resolve',
                'method' => 'GET',
                'path' => '/api/capell/pages/resolve',
                'version' => 'v1',
                'auth' => 'public',
                'readOnly' => true,
                'canonical' => false,
                'compatibility' => 'legacy',
                'signedContextParameters' => ['site', 'language'],
                'publicSafety' => ['sanitized-html', 'no-authoring-surface', 'no-secrets'],
            ],
        ],
    ]);
    $publicRouteNames = data_get($manifest, 'security.publicSurface.routeNames', []);

    throw_unless(is_iterable($publicRouteNames), RuntimeException::class, 'Expected API public route names to be iterable.');

    expect($apiEndpoints)
        ->toHaveCount(2)
        ->and(collect($apiEndpoints)->pluck('routeName')->all())->toBe([
            'capell-api.v1.pages.resolve',
            'capell-api.pages.resolve',
        ])
        ->and(collect($apiEndpoints)->pluck('routeName')->all())->each->toBeIn($publicRouteNames)
        ->and(collect($apiEndpoints)->pluck('type')->unique()->values()->all())->toBe(['public-api-endpoint'])
        ->and(data_get(collect($apiEndpoints)->firstWhere('canonical', true), 'routeName'))->toBe('capell-api.v1.pages.resolve');

    expect($contributions)
        ->toContain([
            'type' => 'health-check',
            'class' => ApiHealthCheck::class,
        ]);

    expect(class_implements(ApiRoutesContribution::class))->toContain(RegistersExtensionRoute::class)
        ->and(class_implements(ApiHealthCheck::class))->toContain(ChecksExtensionHealth::class);
});

it('keeps raw json screenshots as runner evidence instead of marketplace media', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $screenshotContract = capell_json_file_array($packagePath . '/docs/screenshots.json');

    $marketplaceScreenshots = data_get($manifest, 'marketplace.screenshots', []);
    $screenshotEntries = data_get($screenshotContract, 'entries', []);

    throw_unless(is_array($marketplaceScreenshots), RuntimeException::class, 'Expected marketplace screenshots.');
    throw_unless(is_array($screenshotEntries), RuntimeException::class, 'Expected screenshot contract entries.');

    $marketplacePaths = collect($marketplaceScreenshots)
        ->map(static fn (mixed $screenshot): mixed => is_array($screenshot) ? ($screenshot['path'] ?? null) : null)
        ->filter(static fn (mixed $path): bool => is_string($path))
        ->values();

    $runnerScreenshotPaths = collect($screenshotEntries)
        ->flatMap(static function (mixed $entry): array {
            if (! is_array($entry)) {
                return [];
            }

            return [
                $entry['screenshotPath'] ?? null,
                $entry['darkScreenshotPath'] ?? null,
            ];
        })
        ->filter(static fn (mixed $path): bool => is_string($path))
        ->values();

    expect(data_get($screenshotContract, 'generatedFor'))->toBe('deployment-screenshot-runner')
        ->and(data_get($screenshotContract, 'outputDirectory'))->toBe('packages/api/docs/screenshots')
        ->and($runnerScreenshotPaths->all())->not->toBeEmpty()
        ->and($runnerScreenshotPaths->every(
            static fn (string $path): bool => str_starts_with($path, 'packages/api/docs/screenshots/'),
        ))->toBeTrue()
        ->and($marketplacePaths->all())->toBe(['docs/assets/marketplace/extension-card.jpg'])
        ->and($marketplacePaths->intersect($runnerScreenshotPaths)->all())->toBe([])
        ->and($marketplacePaths->every(
            static fn (string $path): bool => str_starts_with($path, 'docs/assets/marketplace/'),
        ))->toBeTrue();
});
