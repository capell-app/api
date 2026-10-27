<?php

declare(strict_types=1);

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Actions\ResolveApiRequestContextAction;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Database\Factories\TranslationFactory;
use Capell\Core\Enums\ContentStructure;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadResolver;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\getJson;

require_once dirname(__DIR__, 5) . '/tests/Packages/Support/PublicOutputSafety.php';

it('returns default page fields without layout', function (): void {
    [$pageUrl] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p>Terms content</p>',
    ]);

    getJson(apiResolveUrl(['url' => $pageUrl->url]))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'url' => '/terms',
                'title' => 'Terms',
                'content' => '<p>Terms content</p>',
            ],
        ]);
});

it('serves the v1 route and emits public api contract headers', function (): void {
    [$pageUrl, $page, $language, $site] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p>Terms content</p>',
    ]);

    URL::useOrigin('https://example.com');

    $v1Response = getJson(route('capell-api.v1.pages.resolve', ['url' => $pageUrl->url]))
        ->assertOk()
        ->assertHeader('X-Capell-Api-Version', 'v1')
        ->assertHeader('X-Capell-Cache-Tags', apiExpectedCacheTags($site, $language, $page))
        ->assertJsonPath('data.url', '/terms')
        ->assertJsonPath('data.title', 'Terms');

    assertCapellPublicOutputIsSafe($v1Response, 'Capell API v1 page resolve response');

    $legacyResponse = getJson(route('capell-api.pages.resolve', ['url' => $pageUrl->url]))
        ->assertOk()
        ->assertHeader('X-Capell-Api-Version', 'v1')
        ->assertHeader('X-Capell-Cache-Tags', apiExpectedCacheTags($site, $language, $page))
        ->assertJsonPath('data.url', '/terms');

    assertCapellPublicOutputIsSafe($legacyResponse, 'Capell API legacy page resolve response');
});

it('emits deterministic etags and honors conditional requests', function (): void {
    [$pageUrl] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p>Terms content</p>',
    ]);

    $response = getJson(apiResolveUrl(['url' => $pageUrl->url]))
        ->assertOk()
        ->assertHeader('X-Capell-Api-Version', 'v1')
        ->assertHeader('ETag');

    $etag = $response->baseResponse->headers->get('ETag');

    expect($etag)->toBeString()
        ->and($etag)->not->toBe('');

    getJson(apiResolveUrl(['url' => $pageUrl->url]), ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertHeader('ETag', $etag)
        ->assertHeader('X-Capell-Api-Version', 'v1')
        ->assertContent('');

    $changedProjectionResponse = getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'fields' => 'title',
    ]), ['If-None-Match' => $etag])
        ->assertOk()
        ->assertHeader('X-Capell-Api-Version', 'v1')
        ->assertJsonPath('data.title', 'Terms');

    expect($changedProjectionResponse->baseResponse->headers->get('ETag'))->not->toBe($etag);
});

it('returns only requested fields', function (): void {
    [$pageUrl] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p>Terms content</p>',
    ]);

    getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'fields' => 'title,unknown',
    ]))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'title' => 'Terms',
            ],
        ]);
});

it('includes selected layout containers without html', function (): void {
    [$pageUrl, $page, $language] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p>Terms content</p>',
    ]);

    $mainWidget = Widget::factory()->create(['key' => 'main-widget']);
    $sidebarWidget = Widget::factory()->create(['key' => 'sidebar-widget']);

    TranslationFactory::new()
        ->translatable($mainWidget)
        ->language($language)
        ->create([
            'title' => 'Main Widget',
            'content' => '<p>Main widget</p>',
        ]);

    $layout = Layout::factory()->site($page->site)->create([
        'key' => 'article',
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $mainWidget->key, 'occurrence' => 1]]],
            'sidebar' => ['widgets' => [['widget_key' => $sidebarWidget->key, 'occurrence' => 1]]],
        ],
    ]);

    $page->update(['layout_id' => $layout->id]);

    getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'include' => 'layout',
        'containers' => 'main',
    ]))
        ->assertOk()
        ->assertJsonPath('data.layout.key', 'article')
        ->assertJsonPath('data.layout.containers.0.key', 'main')
        ->assertJsonCount(1, 'data.layout.containers')
        ->assertJsonPath('data.layout.containers.0.widgets.0.key', 'main-widget')
        ->assertJsonMissingPath('data.layout.containers.0.widgets.0.html');
});

it('treats all containers as the full layout graph', function (): void {
    [$pageUrl, $page] = createPublicApiPage('/terms');

    $mainWidget = Widget::factory()->create(['key' => 'main-widget']);
    $sidebarWidget = Widget::factory()->create(['key' => 'sidebar-widget']);

    $layout = Layout::factory()->site($page->site)->create([
        'key' => 'article',
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $mainWidget->key, 'occurrence' => 1]]],
            'sidebar' => ['widgets' => [['widget_key' => $sidebarWidget->key, 'occurrence' => 1]]],
        ],
    ]);

    $page->update(['layout_id' => $layout->id]);

    getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'include' => 'layout',
        'containers' => 'all',
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data.layout.containers')
        ->assertJsonPath('data.layout.containers.0.key', 'main')
        ->assertJsonPath('data.layout.containers.1.key', 'sidebar');
});

it('rejects layout graphs that exceed configured container and widget bounds', function (): void {
    [$pageUrl, $page] = createPublicApiPage('/bounded-layout');
    $firstWidget = Widget::factory()->create(['key' => 'first-widget']);
    $secondWidget = Widget::factory()->create(['key' => 'second-widget']);
    $layout = Layout::factory()->site($page->site)->create([
        'key' => 'bounded',
        'containers' => [
            'main' => ['widgets' => [
                ['widget_key' => $firstWidget->key, 'occurrence' => 1],
                ['widget_key' => $secondWidget->key, 'occurrence' => 1],
            ]],
            'sidebar' => ['widgets' => []],
        ],
    ]);
    $page->update(['layout_id' => $layout->id]);

    config()->set('capell-api.public_pages.max_layout_containers', 1);

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'include' => 'layout', 'containers' => 'all']))
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Layout container count exceeds the API limit; request explicit containers.']);

    config()->set('capell-api.public_pages.max_layout_containers', 12);
    config()->set('capell-api.public_pages.max_widgets_per_container', 1);

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'include' => 'layout', 'containers' => 'main']))
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Layout widget count exceeds the API limit.']);
});

it('rejects serialized responses above the configured byte ceiling', function (): void {
    [$pageUrl] = createPublicApiPage('/oversized', ['content' => '<p>' . str_repeat('Large response ', 200) . '</p>']);
    config()->set('capell-api.public_pages.max_response_bytes', 1024);

    getJson(apiResolveUrl(['url' => $pageUrl->url]))
        ->assertStatus(413)
        ->assertExactJson(['message' => 'API response exceeds the configured byte limit.']);
});

it('includes layout html and sanitizes unsafe html strings', function (): void {
    [$pageUrl, $page, $language] = createPublicApiPage('/terms', [
        'title' => 'Terms',
        'content' => '<p onclick="alert(1)"><a href="javascript:alert(2)">Terms</a></p><script>alert(3)</script>',
        'meta' => [
            'description' => '<span onclick="alert(1)"><a href="javascript:alert(2)">Meta</a></span><script src="/bad.js">',
        ],
    ]);

    $block = Widget::factory()->create(['key' => 'hero']);

    $layout = Layout::factory()->site($page->site)->create([
        'key' => 'article',
        'meta' => ['summary' => '<strong onclick="alert(1)">Layout</strong><script>bad</script>'],
        'containers' => [
            'main' => [
                'summary' => '<em onmouseover="alert(1)">Container</em><script',
                'widgets' => [['widget_key' => $block->key, 'occurrence' => 1]],
            ],
        ],
    ]);

    $page->update(['layout_id' => $layout->id]);

    app()->bind(PublicLayoutWidgetPayloadResolver::class, fn (): PublicLayoutWidgetPayloadResolver => new class implements PublicLayoutWidgetPayloadResolver
    {
        /**
         * @return array<string, mixed>
         */
        public function data(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): array
        {
            return [
                'content' => '<p onmouseover="alert(1)"><a href="javascript:alert(2)">Widget</a></p><script>alert(3)</script>',
                'nested' => ['content' => '<div onclick=alert(4)><iframe srcdoc="<script>alert(5)</script>"></iframe>Nested</div>'],
            ];
        }

        public function html(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): string
        {
            return '<section onclick="alert(6)"><a href="javascript:alert(7)">Hero</a><script>alert(8)</script></section>';
        }
    });

    $response = getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'fields' => 'url,title,content,meta',
        'include' => 'layout.html',
        'containers' => 'main',
    ]))
        ->assertOk()
        ->assertJsonPath('data.content', '<p><a>Terms</a></p>')
        ->assertJsonPath('data.meta.description', '<span><a>Meta</a></span>')
        ->assertJsonPath('data.layout.meta', [])
        ->assertJsonPath('data.layout.containers.0.meta', [])
        ->assertJsonPath('data.layout.containers.0.widgets.0.data.content', '<p><a>Widget</a></p>')
        ->assertJsonPath('data.layout.containers.0.widgets.0.data.nested.content', '<div>Nested</div>')
        ->assertJsonPath('data.layout.containers.0.widgets.0.html', '<section><a>Hero</a></section>');

    assertCapellPublicOutputIsSafe($response, 'Capell API layout HTML response');
});

it('rejects unbounded layout html requests', function (): void {
    [$pageUrl, $page] = createPublicApiPage('/terms');
    $block = Widget::factory()->create(['key' => 'hero']);

    $layout = Layout::factory()->site($page->site)->create([
        'key' => 'article',
        'containers' => [
            'main' => ['widgets' => [['widget_key' => $block->key, 'occurrence' => 1]]],
        ],
    ]);

    $page->update(['layout_id' => $layout->id]);

    getJson(apiResolveUrl([
        'url' => $pageUrl->url,
        'include' => 'layout.html',
        'containers' => 'all',
    ]))
        ->assertStatus(422)
        ->assertExactJson(['message' => 'layout.html requires explicit bounded containers.']);
});

it('returns not found for missing pages', function (): void {
    createPublicApiPage('/terms');

    getJson(apiResolveUrl(['url' => '/missing']))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Page not found']);
});

it('resolves the site from the request host and blocks unsigned explicit site selection', function (): void {
    [, , , $site] = createPublicApiPage('/terms');
    [$otherPageUrl, , , $otherSite] = createPublicApiPage('/terms', [
        'title' => 'Other Terms',
    ], 'other.example.com');

    getJson(apiResolveUrl(['url' => '/terms', 'site' => $otherSite->getKey()]))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden']);

    getJson(apiResolveUrl(['url' => '/terms', 'site' => $otherSite->getKey()], 'other.example.com', signed: true))
        ->assertOk()
        ->assertJsonPath('data.url', $otherPageUrl->url)
        ->assertJsonPath('data.title', 'Other Terms');

    expect($site->getKey())->not->toBe($otherSite->getKey());
});

it('resolves api request context and cache tags directly from the request host', function (): void {
    [$pageUrl, $page, $language, $site] = createPublicApiPage('/terms');

    $request = Request::create('https://example.com/api/capell/pages/resolve?url=' . urlencode($pageUrl->url));

    $context = ResolveApiRequestContextAction::run($request);

    expect($context->found())->toBeTrue()
        ->and($context->resolvedSite()->is($site))->toBeTrue()
        ->and($context->resolvedLanguage()->is($language))->toBeTrue()
        ->and($context->urlPath)->toBe('/terms')
        ->and($context->cacheTags($page))->toBe([
            'api',
            'site:' . apiScalarKey($site->getKey()),
            'language:' . apiScalarKey($language->getKey()),
            'page:' . apiScalarKey($page->getKey()),
        ]);
});

it('rejects unsigned explicit site selection in the api request context action', function (): void {
    [, , , $site] = createPublicApiPage('/terms');

    $request = Request::create('https://example.com/api/capell/pages/resolve?url=/terms&site=' . apiScalarKey($site->getKey()));

    expect(ResolveApiRequestContextAction::run($request)->isForbidden())->toBeTrue();
});

it('blocks unsigned explicit language selection', function (): void {
    [$pageUrl] = createPublicApiPage('/terms');

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'language' => 'en']))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden']);

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'language' => 'en'], signed: true))
        ->assertOk()
        ->assertJsonPath('data.url', $pageUrl->url);
});

it('scopes signed explicit language selection to the resolved site', function (): void {
    [$pageUrl] = createPublicApiPage('/terms');
    [, , $otherLanguage] = createPublicApiPage('/terms', [
        'title' => 'Other Terms',
    ], 'other.example.com');

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'language' => $otherLanguage->getKey()], signed: true))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Page not found']);
});

it('allows signed explicit language selection for another language on the resolved site', function (): void {
    [$pageUrl, $page, , $site] = createPublicApiPage('/terms');
    $french = Language::factory()->french()->create();

    SiteDomain::factory()
        ->site($site)
        ->language($french)
        ->create([
            'domain' => 'fr.example.com',
            'path' => null,
            'scheme' => null,
        ]);

    TranslationFactory::new()
        ->translatable($page)
        ->language($french)
        ->create([
            'title' => 'Conditions',
            'content' => '<p>Conditions en francais</p>',
            'meta' => ['description' => 'Conditions meta'],
        ]);

    PageUrl::factory()
        ->site($site)
        ->language($french)
        ->page($page)
        ->create(['url' => $pageUrl->url]);

    getJson(apiResolveUrl(['url' => $pageUrl->url, 'language' => 'fr'], signed: true))
        ->assertOk()
        ->assertJsonPath('data.url', $pageUrl->url)
        ->assertJsonPath('data.title', 'Conditions');
});

it('uses the requested public URL path when resolving path-prefixed site domains', function (): void {
    [$pageUrl] = createPublicApiPage('/terms', [
        'title' => 'Tenant Terms',
    ], siteDomainPath: '/tenant');

    getJson(apiResolveUrl(['url' => '/tenant/terms']))
        ->assertOk()
        ->assertJsonPath('data.url', $pageUrl->url)
        ->assertJsonPath('data.title', 'Tenant Terms');
});

it('does not hydrate wildcard site domains when an exact host matches', function (): void {
    [$pageUrl] = createPublicApiPage('/terms', [
        'title' => 'Exact Host Terms',
    ], domain: 'exact.example.com');

    foreach (range(1, 15) as $index) {
        createPublicApiPage('/wildcard-' . $index, [
            'title' => 'Wildcard ' . $index,
        ], domain: '*.wildcard-' . $index . '.example.com');
    }

    $retrievedSiteDomains = 0;
    SiteDomain::retrieved(function () use (&$retrievedSiteDomains): void {
        $retrievedSiteDomains++;
    });

    getJson(apiResolveUrl(['url' => $pageUrl->url], host: 'exact.example.com'))
        ->assertOk()
        ->assertJsonPath('data.title', 'Exact Host Terms');

    expect($retrievedSiteDomains)->toBeLessThanOrEqual(2);
});

it('does not allow signed context URLs to change the target page URL', function (): void {
    [$pageUrl, , , $site] = createPublicApiPage('/terms');
    createPublicApiPage('/privacy', domain: 'privacy.example.com');

    $signedUrl = apiResolveUrl([
        'url' => $pageUrl->url,
        'site' => $site->getKey(),
    ], signed: true);

    getJson(str_replace('url=%2Fterms', 'url=%2Fprivacy', $signedUrl))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden']);
});

it('does not serve the api when the package is not installed', function (): void {
    createPublicApiPage('/terms');
    CapellCore::forcePackageInstalled(ApiServiceProvider::$packageName, false);

    getJson(apiResolveUrl(['url' => '/terms']))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Page not found']);
});

/**
 * @param  array<string, mixed>  $translation
 * @return array{0: PageUrl, 1: Page, 2: Language, 3: Site}
 */
function createPublicApiPage(string $url, array $translation = [], ?string $domain = 'example.com', ?string $siteDomainPath = null): array
{
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    SiteDomain::factory()
        ->site($site)
        ->language($language)
        ->create([
            'domain' => $domain,
            'path' => $siteDomainPath,
            'scheme' => null,
        ]);
    $page = Page::factory()
        ->site($site)
        ->withTranslations($language, [
            'title' => $translation['title'] ?? 'Terms',
            'content' => $translation['content'] ?? '<p>Terms content</p>',
            'meta' => $translation['meta'] ?? ['description' => 'Terms meta'],
        ])
        ->create();

    $pageUrl = PageUrl::factory()
        ->site($site)
        ->language($language)
        ->page($page)
        ->create(['url' => $url]);

    return [$pageUrl, $page, $language, $site];
}

function apiExpectedCacheTags(Site $site, Language $language, Page $page): string
{
    return sprintf(
        'api,site:%s,language:%s,page:%s',
        apiScalarKey($site->getKey()),
        apiScalarKey($language->getKey()),
        apiScalarKey($page->getKey()),
    );
}

function apiScalarKey(mixed $key): string
{
    return is_scalar($key) ? (string) $key : '';
}

/**
 * @param  array<string, mixed>  $parameters
 */
function apiResolveUrl(array $parameters = [], string $host = 'example.com', bool $signed = false): string
{
    URL::useOrigin('https://' . $host);

    if ($signed) {
        $signedUrl = URL::signedRoute('capell-api.pages.resolve', array_diff_key($parameters, array_flip(['fields', 'include', 'containers'])));
        $ignoredParameters = array_intersect_key($parameters, array_flip(['fields', 'include', 'containers']));

        return $ignoredParameters === []
            ? $signedUrl
            : $signedUrl . '&' . http_build_query($ignoredParameters);
    }

    return route('capell-api.pages.resolve', $parameters);
}

it('removes reserved editor envelopes from native block content without losing public values', function (): void {
    $blocks = [['type' => 'chart', 'data' => ['title' => 'Workshop attendance', 'series' => [84, 128], '__capell' => ['instance_id' => 'private-editor-instance', 'state_version' => 1]]]];
    [$pageUrl, $page] = createPublicApiPage('/workshops', ['title' => 'Workshops', 'content' => $blocks]);
    $page->forceFill(['content_structure_override' => ContentStructure::Blocks->value])->save();

    $response = getJson(apiResolveUrl(['url' => $pageUrl->url]))
        ->assertOk()
        ->assertExactJson(['data' => ['url' => '/workshops', 'title' => 'Workshops', 'content' => [['type' => 'chart', 'data' => ['title' => 'Workshop attendance', 'series' => [84, 128]]]]]])
        ->assertHeader('X-Capell-Api-Version', 'v1');

    expect($response->getContent())->not->toContain('__capell', 'private-editor-instance', 'state_version');
    assertCapellPublicOutputIsSafe($response, 'Native block content API response');
});

it('invalidates a cached response when sanitized serializer output changes with unchanged stored fields', function (): void {
    [$pageUrl] = createPublicApiPage('/terms');
    BuildPublicPagePayloadAction::shouldRun()->twice()->andReturn(
        ['url' => '/terms', 'title' => 'Before policy repair'],
        ['url' => '/terms', 'title' => 'After policy repair'],
    );
    $first = getJson(apiResolveUrl(['url' => $pageUrl->url]))->assertOk();
    $etag = $first->baseResponse->headers->get('ETag');
    $second = getJson(apiResolveUrl(['url' => $pageUrl->url]), ['If-None-Match' => $etag])
        ->assertOk()
        ->assertExactJson(['data' => ['url' => '/terms', 'title' => 'After policy repair']]);
    expect($second->baseResponse->headers->get('ETag'))->not->toBe($etag);
});

it('keeps documented v1 signed page context fixed while allowing projection changes', function (): void {
    [$pageUrl, $page, $language, $site] = createPublicApiPage('/terms');
    PageUrl::factory()->site($site)->language($language)->page($page)->create(['url' => '/second-page']);
    URL::useOrigin('https://example.com');
    $parameters = ['url' => '/terms', 'site' => $site->getKey(), 'language' => $language->getKey()];
    $signedUrl = URL::temporarySignedRoute('capell-api.v1.pages.resolve', now()->addMinutes(15), $parameters);

    getJson($signedUrl . '&fields=url,title&include=layout&containers=main')
        ->assertOk()->assertJsonPath('data.url', '/terms')->assertJsonMissingPath('data.content');

    foreach (['url' => '/second-page', 'site' => 999999, 'language' => 999999] as $key => $value) {
        $parts = parse_url($signedUrl);
        parse_str($parts['query'] ?? '', $query);
        $query[$key] = $value;
        getJson('https://example.com/api/capell/v1/pages/resolve?' . http_build_query($query))->assertForbidden();
    }

    $parameters['url'] = '/second-page';
    getJson(URL::temporarySignedRoute('capell-api.v1.pages.resolve', now()->addMinutes(15), $parameters))
        ->assertOk()->assertJsonPath('data.url', '/second-page');
});
