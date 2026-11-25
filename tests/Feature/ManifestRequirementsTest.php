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

    $headlessPaths = ['pages', 'pages/resolve', 'collections/{type}', 'search', 'navigation', 'media'];
    $headlessNames = ['pages', 'resolve', 'collections', 'search', 'navigation', 'media'];
    $headlessRoutes = array_map(static fn (string $name): string => 'capell-api.v1.' . $name, $headlessNames);
    $headlessSecurityRoutes = array_map(static fn (string $path, string $name): string => 'GET /api/v1/' . $path . ' (capell-api.v1.' . $name . ')', $headlessPaths, $headlessNames);
    sort($headlessSecurityRoutes);
    $headlessEndpoints = array_map(static fn (string $path, string $name): array => [
        'type' => 'public-api-endpoint', 'name' => 'headless-v1-' . $name,
        'routeName' => 'capell-api.v1.' . $name, 'method' => 'GET', 'path' => '/api/v1/' . $path,
        'version' => 'v1', 'auth' => 'public', 'readOnly' => true, 'canonical' => true,
        'signedContextParameters' => ['site', 'language'],
        'publicSafety' => ['sanitized-html', 'no-authoring-surface', 'no-secrets'],
    ], $headlessPaths, $headlessNames);

    expect($manifest)
        ->toHaveKey('manifest-version', 3)
        ->toHaveKey('name', 'capell-app/api')
        ->toHaveKey('namespace', 'Capell\\Api')
        ->and(data_get($manifest, 'security.publicSurface.routeNames'))->toBe([
            'GET /api/capell/v1/pages/resolve (capell-api.v1.pages.resolve)',
            ...$headlessSecurityRoutes,
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
            'capell-api.v1.pages.resolve',
            ...$headlessRoutes,
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
                'canonical' => false,
                'signedContextParameters' => ['site', 'language'],
                'publicSafety' => ['sanitized-html', 'no-authoring-surface', 'no-secrets'],
            ],
            ...$headlessEndpoints,
        ],
    ]);
    $publicRouteNames = data_get($manifest, 'security.publicSurface.routeNames', []);

    throw_unless(is_iterable($publicRouteNames), RuntimeException::class, 'Expected API public route names to be iterable.');

    expect($apiEndpoints)
        ->toHaveCount(7)
        ->and(collect($apiEndpoints)->pluck('routeName')->all())->toBe([
            'capell-api.v1.pages.resolve',
            ...$headlessRoutes,
        ])
        ->and(collect($apiEndpoints)->pluck('routeName')->all())->each->toBeIn(collect($publicRouteNames)->map(static fn (mixed $name): string => preg_replace('/^[A-Z|]+ \S+ \((.+)\)$/', '$1', is_string($name) ? $name : '') ?? '')->all())
        ->and(collect($apiEndpoints)->pluck('type')->unique()->values()->all())->toBe(['public-api-endpoint'])
        ->and(data_get(collect($apiEndpoints)->firstWhere('canonical', true), 'routeName'))->toBe('capell-api.v1.pages');

    expect($contributions)
        ->toContain([
            'type' => 'health-check',
            'class' => ApiHealthCheck::class,
        ]);

    expect(class_implements(ApiRoutesContribution::class))->toContain(RegistersExtensionRoute::class)
        ->and(class_implements(ApiHealthCheck::class))->toContain(ChecksExtensionHealth::class);
});

it('keeps runner json evidence separate from committed marketplace media', function (): void {
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

    expect(data_get($screenshotContract, 'generatedFor'))->toBe('shared-capell-screenshot-runner')
        ->and(data_get($screenshotContract, 'outputDirectory'))->toBe('packages/api/docs/screenshots')
        ->and($runnerScreenshotPaths->all())->not->toBeEmpty()
        ->and($runnerScreenshotPaths->every(
            static fn (string $path): bool => str_starts_with($path, 'packages/api/docs/screenshots/'),
        ))->toBeTrue()
        // The listing leads with the runner's receipt-backed captures rather than
        // copies of them, so the gallery shows evidence that can be re-verified.
        ->and($marketplacePaths->all())->toBe(['docs/screenshots/nextjs-starter-home.png', 'docs/screenshots/nextjs-starter-directory.png', 'docs/screenshots/nextjs-starter-article.png', 'docs/screenshots/page-resolve-success-json.png', 'docs/screenshots/page-resolve-layout-json.png', 'docs/assets/marketplace/extension-card.jpg'])
        ->and($marketplacePaths
            ->filter(static fn (string $path): bool => str_starts_with($path, 'docs/screenshots/'))
            ->map(static fn (string $path): string => 'packages/api/' . $path)
            ->reject(static fn (string $path): bool => $runnerScreenshotPaths->contains($path))
            ->all())->toBe([]);
});

it('declares non-2xx json runner evidence with explicit expected statuses', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $screenshotContract = capell_json_file_array($packagePath . '/docs/screenshots.json');
    $screenshotEntries = data_get($screenshotContract, 'entries', []);

    throw_unless(is_array($screenshotEntries), RuntimeException::class, 'Expected screenshot contract entries.');

    $entriesById = collect($screenshotEntries)->keyBy('id');

    expect($entriesById->get('page-resolve-success-json'))->toMatchArray([
        'expectedStatus' => 200,
        'required' => true,
    ])
        ->and($entriesById->get('page-resolve-layout-json'))->toMatchArray([
            'expectedStatus' => 200,
            'required' => true,
        ])
        ->and($entriesById->get('page-resolve-forbidden-json'))->toMatchArray([
            'expectedStatus' => 403,
            'required' => false,
        ])
        ->and($entriesById->get('page-resolve-not-found-json'))->toMatchArray([
            'expectedStatus' => 404,
            'required' => false,
        ])
        ->and(collect([
            $entriesById->get('page-resolve-forbidden-json'),
            $entriesById->get('page-resolve-not-found-json'),
        ])->every(
            static fn (mixed $entry): bool => is_array($entry)
                && is_string($entry['notes'] ?? null)
                && str_contains($entry['notes'], 'expectedStatus'),
        ))->toBeTrue();
});
