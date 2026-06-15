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

    $healthClasses = collect(data_get($manifest, 'healthChecks', []))
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
        ->and($healthClasses)->toBe([ApiHealthCheck::class])
        ->and(data_get($manifest, 'healthChecks.0.key'))->toBe('api.package-health')
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->toBe([
            'public-api-endpoint',
        ]);

    expect($contributions)
        ->toContain([
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
        ])
        ->toContain([
            'type' => 'health-check',
            'class' => ApiHealthCheck::class,
        ]);

    expect(class_implements(ApiRoutesContribution::class))->toContain(RegistersExtensionRoute::class)
        ->and(class_implements(ApiHealthCheck::class))->toContain(ChecksExtensionHealth::class);
});
