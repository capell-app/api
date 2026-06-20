<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

it('documents the public page resolver contract in openapi', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = api_openapi_json_file_array($packagePath . '/capell.json');
    $openApi = api_openapi_yaml_file_array($packagePath . '/docs/openapi.yaml');
    $pageApiDocs = File::get($packagePath . '/docs/page-api.md');
    $docsIndex = File::get($packagePath . '/docs/README.md');

    $apiEndpoints = data_get($manifest, 'contributes.0.apiEndpoints', []);

    throw_unless(is_array($apiEndpoints), RuntimeException::class, 'Expected API endpoint metadata.');

    $documentedPaths = array_keys(api_openapi_array_value($openApi, 'paths'));
    $components = api_openapi_array_value($openApi, 'components');
    $schemas = api_openapi_array_value($components, 'schemas');
    $manifestPaths = collect($apiEndpoints)
        ->map(static fn (mixed $endpoint): mixed => is_array($endpoint) ? ($endpoint['path'] ?? null) : null)
        ->filter(static fn (mixed $path): bool => is_string($path))
        ->values()
        ->all();

    expect($openApi)
        ->toHaveKey('openapi', '3.1.0')
        ->and(data_get($openApi, 'info.version'))->toBe('v1')
        ->and($documentedPaths)->toBe($manifestPaths)
        ->and(data_get($openApi, 'paths./api/capell/v1/pages/resolve.get.operationId'))->toBe('resolvePublicPageV1')
        ->and(data_get($openApi, 'paths./api/capell/pages/resolve.get.operationId'))->toBe('resolvePublicPageLegacy')
        ->and(array_keys($schemas))->toContain(
            'PageResponse',
            'PageData',
            'LayoutGraph',
            'LayoutContainer',
            'Widget',
            'ErrorResponse',
        )
        ->and(data_get($openApi, 'components.responses.PageResolved.headers.X-Capell-Api-Version.$ref'))->toBe('#/components/headers/ApiVersion')
        ->and(data_get($openApi, 'components.responses.PageResolved.headers.X-Capell-Cache-Tags.$ref'))->toBe('#/components/headers/CacheTags')
        ->and(data_get($openApi, 'components.responses.PageResolved.headers.ETag.$ref'))->toBe('#/components/headers/ETag')
        ->and(data_get($openApi, 'components.responses.NotModified.headers.ETag.$ref'))->toBe('#/components/headers/ETag')
        ->and(data_get($openApi, 'components.responses.PageNotFound.content.application/json.schema.$ref'))->toBe('#/components/schemas/ErrorResponse')
        ->and(data_get($openApi, 'components.responses.InvalidLayoutHtmlRequest.content.application/json.examples.unboundedLayoutHtml.value.message'))->toBe('layout.html requires explicit bounded containers.')
        ->and($pageApiDocs)->toContain('openapi.yaml')
        ->and($docsIndex)->toContain('OpenAPI contract');
});

/**
 * @return array<string, mixed>
 */
function api_openapi_json_file_array(string $path): array
{
    $decodedJson = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

    throw_unless(is_array($decodedJson), RuntimeException::class, sprintf('Expected %s to decode to an array.', $path));

    return $decodedJson;
}

/**
 * @return array<string, mixed>
 */
function api_openapi_yaml_file_array(string $path): array
{
    $decodedYaml = Yaml::parseFile($path);

    throw_unless(is_array($decodedYaml), RuntimeException::class, sprintf('Expected %s to decode to an array.', $path));

    return $decodedYaml;
}

/**
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function api_openapi_array_value(array $values, string $key): array
{
    $value = $values[$key] ?? null;

    throw_unless(is_array($value), RuntimeException::class, sprintf('Expected %s to be an array.', $key));

    return $value;
}
