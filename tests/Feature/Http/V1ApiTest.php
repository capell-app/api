<?php

declare(strict_types=1);

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Support\ApiLimits;
use Capell\Api\Tests\Fixtures\ApiTokenUser;
use Capell\Core\Contracts\PublicRead\PublicPageReadEligibility;
use Capell\Core\Contracts\SiteAccessPolicyProvider;
use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Data\SiteAccessContextData;
use Capell\Core\Data\SiteAccessPolicyData;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Capell\Core\Support\SiteAccess\SiteAccessPolicyRegistry;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    URL::useOrigin('https://example.com');
    $this->language = Language::factory()->english()->create();
    $this->site = Site::factory()->default()->create(['language_id' => $this->language->id]);
    SiteDomain::factory()->site($this->site)->language($this->language)->create(['domain' => 'example.com', 'scheme' => null, 'path' => null]);
});

/** @return array{PageUrl, Page} */
function v1Page(Site $site, Language $language, string $path, string $title = 'Welcome', string $content = '<p>Hello from Capell</p>'): array
{
    $page = Page::factory()->site($site)->withTranslations($language, ['title' => $title, 'content' => $content])->create();
    $url = $page->pageUrls()->where('language_id', $language->id)->firstOrFail();
    $url->update(['url' => $path]);

    return [$url, $page];
}

it('resolves safe v1 pages and carries revalidation and surrogate headers', function (): void {
    v1Page($this->site, $this->language, '/hello', content: '<p>Hello</p><script>private()</script>');
    $response = getJson('https://example.com/api/v1/pages/resolve?url=/hello')->assertOk()
        ->assertExactJson(['data' => ['url' => '/hello', 'title' => 'Welcome', 'content' => '<p>Hello</p>']])
        ->assertHeader('Surrogate-Key', 'site-' . $this->site->id)->assertHeader('ETag');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    getJson('https://example.com/api/v1/pages/resolve?url=/hello', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
});

it('paginates collections stably without exposing cursor positions', function (): void {
    [, $first] = v1Page($this->site, $this->language, '/first');
    v1Page($this->site, $this->language, '/second');
    $blueprint = $first->blueprint;
    throw_unless($blueprint instanceof Blueprint, LogicException::class);
    $type = $blueprint->key;
    $one = getJson('https://example.com/api/v1/collections/' . $type . '?limit=1')->assertOk()->assertJsonCount(1, 'data');
    $cursor = $one->json('meta.next_cursor');
    expect($cursor)->toBeString()->not->toBe('');
    throw_unless(is_string($cursor), LogicException::class);
    getJson('https://example.com/api/v1/collections/' . $type . '?limit=1&cursor=' . urlencode($cursor))
        ->assertOk()->assertJsonPath('data.0.url', '/second')->assertJsonPath('meta.next_cursor', null);
    getJson('https://example.com/api/v1/pages?limit=1&cursor=' . urlencode($cursor))->assertUnprocessable();
    getJson('https://example.com/api/v1/pages?cursor=forged')->assertUnprocessable();
});

it('rejects non-whitelisted filters and excessive pagination', function (): void {
    getJson('https://example.com/api/v1/pages?filter[deleted_at]=1')->assertUnprocessable();
    getJson('https://example.com/api/v1/pages?limit=101')->assertUnprocessable();
    getJson('https://example.com/api/v1/pages?filter[path][]=bad')->assertUnprocessable();
});

it('filters and searches only public page representations', function (): void {
    v1Page($this->site, $this->language, '/hello', 'Hello');
    v1Page($this->site, $this->language, '/other', 'Other');
    getJson('https://example.com/api/v1/pages?filter[path]=/other')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', '/other');
    getJson('https://example.com/api/v1/search?q=HELLO')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Hello');
});

it('excludes unpublished scheduled expired and deleted pages everywhere', function (string $state): void {
    [, $page] = v1Page($this->site, $this->language, '/private', 'Private');
    match ($state) {
        'draft' => $page->update(['visible_from' => '9999-12-31 00:00:00']),
        'scheduled' => $page->update(['visible_from' => now()->addDay()]),
        'expired' => $page->update(['visible_until' => now()->subMinute()]),
        'deleted' => $page->delete(),
        default => throw new InvalidArgumentException('Unknown publication state'),
    };
    getJson('https://example.com/api/v1/pages/resolve?url=/private')->assertNotFound();
    foreach (['pages', 'navigation', 'media', 'search?q=Private'] as $resource) {
        getJson('https://example.com/api/v1/' . $resource)->assertOk()->assertJsonCount(0, 'data');
    }
})->with(['draft', 'scheduled', 'expired', 'deleted']);

it('requires preview authority and never caches errors or bearer reads', function (): void {
    v1Page($this->site, $this->language, '/hello');
    getJson('https://example.com/api/v1/pages?preview=1')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
    $response = getJson('https://example.com/api/v1/pages', ['Authorization' => 'Bearer invalid', 'If-None-Match' => '*'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->has('ETag'))->toBeFalse();
});

it('projects page navigation and content images without attachment internals', function (): void {
    v1Page($this->site, $this->language, '/hello', content: '<img src="/images/hello.jpg" alt="Hello">');
    v1Page($this->site, $this->language, '/private-image', content: '<img src="https://storage.example/private.jpg?signature=secret" alt="Private">');
    getJson('https://example.com/api/v1/navigation')->assertOk()->assertJsonPath('data.0.url', '/hello')->assertJsonMissingPath('data.0.content');
    getJson('https://example.com/api/v1/media')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', '/images/hello.jpg');
});

it('records deterministic SDK fixtures from real routed API responses', function (): void {
    v1Page($this->site, $this->language, '/', 'Welcome', '<p>Hello from Capell</p>');
    v1Page($this->site, $this->language, '/about', 'About', '<p>An agency site</p>');
    $fixtures = [];
    foreach (['page' => 'pages/resolve?url=%2F', 'pages' => 'pages', 'navigation' => 'navigation', 'media' => 'media', 'search' => 'search?q=Welcome'] as $name => $resource) {
        $response = getJson('https://example.com/api/v1/' . $resource);
        $response->assertOk();
        $fixtures[$name] = $response->json();
    }
    if (getenv('CAPELL_API_RECORD_FIXTURES') === '1') {
        file_put_contents(dirname(__DIR__, 3) . '/sdk/fixtures/api-v1.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
});

it('omits contributor-restricted pages from every public endpoint and legacy alias', function (): void {
    v1Page($this->site, $this->language, '/restricted', 'Restricted');
    app(PublicPageReadEligibilityRegistry::class)->register(new class implements PublicPageReadEligibility
    {
        #[Override]
        public function key(): string
        {
            return 'api-test-deny';
        }

        #[Override]
        public function isPubliclyReadable(PublicPageReadCandidateData $candidate): bool
        {
            return false;
        }

        /** @param list<PublicPageReadCandidateData> $candidates @return list<bool> */
        #[Override]
        public function arePubliclyReadable(array $candidates): array
        {
            return array_fill(0, count($candidates), false);
        }
    });
    foreach (['pages', 'navigation', 'media', 'search?q=Restricted'] as $resource) {
        getJson('https://example.com/api/v1/' . $resource)->assertOk()->assertJsonCount(0, 'data');
    }
    getJson('https://example.com/api/v1/pages/resolve?url=/restricted')->assertNotFound();
    getJson('https://example.com/api/capell/pages/resolve?url=/restricted')->assertNotFound();
});

it('previews nonpublic states only with the matching site and state abilities', function (string $state): void {
    [, $page] = v1Page($this->site, $this->language, '/draft');
    $blueprint = $page->blueprint;
    throw_unless($blueprint instanceof Blueprint, LogicException::class);
    match ($state) {
        'draft' => $page->update(['visible_from' => '9999-12-31 00:00:00']),
        'scheduled' => $page->update(['visible_from' => now()->addDay()]),
        'deleted' => $page->delete(),
        'restricted' => $blueprint->update(['meta' => ['accessible' => false]]),
        default => throw new InvalidArgumentException('Unknown preview state'),
    };
    Relation::morphMap(['api-token-user' => ApiTokenUser::class]);
    $createdUser = User::factory()->create();
    $user = ApiTokenUser::query()->whereKey($createdUser->getKey())->firstOrFail();
    $issued = $user->createToken('API preview', ['api:preview'], now()->addHour());
    $plain = $issued->plainTextToken;
    $token = $issued->accessToken;
    $url = 'https://example.com/api/v1/pages/resolve?url=/draft&preview=1';
    getJson($url, ['Authorization' => 'Bearer ' . $plain])->assertForbidden();
    $abilities = ['api:preview', 'api:preview:site:' . $this->site->id];
    $token->update(['abilities' => $abilities]);
    app(AuthManager::class)->forgetGuards();
    if (in_array($state, ['deleted', 'restricted'], true)) {
        getJson($url, ['Authorization' => 'Bearer ' . $plain])->assertNotFound();
        $abilities[] = 'api:preview:' . $state;
        $token->update(['abilities' => $abilities]);
        app(AuthManager::class)->forgetGuards();
    }
    getJson($url, ['Authorization' => 'Bearer ' . $plain, 'If-None-Match' => '*'])->assertOk()
        ->assertJsonPath('data.url', '/draft')->assertHeader('Cache-Control', 'no-store, private');
    $token->update(['expires_at' => now()->subSecond()]);
    app(AuthManager::class)->forgetGuards();
    getJson($url, ['Authorization' => 'Bearer ' . $plain])->assertForbidden();
    $token->delete();
    app(AuthManager::class)->forgetGuards();
    getJson($url, ['Authorization' => 'Bearer ' . $plain])->assertForbidden();
})->with(['draft', 'scheduled', 'deleted', 'restricted']);

it('never publicly resolves preview sites even through signed context', function (): void {
    v1Page($this->site, $this->language, '/private');
    $this->site->update(['meta' => ['is_preview' => true]]);
    getJson('https://example.com/api/v1/pages/resolve?url=/private')->assertNotFound();
    getJson('https://example.com/api/v1/pages')->assertNotFound();
    getJson(URL::signedRoute('capell-api.v1.resolve', ['site' => $this->site->id, 'url' => '/private']))->assertNotFound();
    getJson('https://example.com/api/capell/pages/resolve?url=/private')->assertNotFound();
});

it('fails closed on every public endpoint while a site access policy is active', function (string $uri): void {
    [, $page] = v1Page($this->site, $this->language, '/locked', 'Locked');
    $type = $page->blueprint?->key;
    app(SiteAccessPolicyRegistry::class)->register(new class implements SiteAccessPolicyProvider
    {
        #[Override]
        public function key(): string
        {
            return 'api-test-lockdown';
        }

        #[Override]
        public function resolve(SiteAccessContextData $context): SiteAccessPolicyData
        {
            return new SiteAccessPolicyData(active: true);
        }
    });
    $response = getJson('https://example.com' . str_replace('{type}', (string) $type, $uri));
    $response->assertNotFound();
    expect($response->getContent())->not->toContain('Locked')->not->toContain('/locked');
})->with([
    'pages' => ['/api/v1/pages'],
    'resolve' => ['/api/v1/pages/resolve?url=/locked'],
    'navigation' => ['/api/v1/navigation'],
    'media' => ['/api/v1/media'],
    'collections' => ['/api/v1/collections/{type}'],
    'search' => ['/api/v1/search?q=Locked'],
    'legacy resolve' => ['/api/capell/v1/pages/resolve?url=/locked'],
]);

it('serves navigation within the byte limit when complete page bodies would exceed it', function (): void {
    config()->set('capell-api.public_pages.max_response_bytes', 20000);
    foreach (range(1, 5) as $index) {
        v1Page($this->site, $this->language, '/big-' . $index, 'Big ' . $index, '<p>' . str_repeat('a', 8000) . '</p><img src="/img/' . $index . '.png" alt="Image ' . $index . '">');
    }
    getJson('https://example.com/api/v1/navigation')->assertOk()->assertJsonCount(5, 'data');
    getJson('https://example.com/api/v1/media')->assertOk()->assertJsonCount(5, 'data');
    getJson('https://example.com/api/v1/pages')->assertStatus(413);
});

it('accounts a projected listing by the bytes it returns, so a response exactly at the ceiling is served', function (string $uri): void {
    foreach (range(1, 80) as $index) {
        v1Page($this->site, $this->language, '/fit-' . $index, 'Fit ' . $index, '<p>Short</p><img src="/img/fit-' . $index . '.png" alt="Fit ' . $index . '">');
    }
    config()->set('capell-api.public_pages.max_response_bytes', 1048576);
    $exact = strlen((string) getJson('https://example.com' . $uri . '?limit=80')->assertOk()->getContent());
    expect($exact)->toBeGreaterThan(1024);

    config()->set('capell-api.public_pages.max_response_bytes', $exact);
    getJson('https://example.com' . $uri . '?limit=80')->assertOk();

    config()->set('capell-api.public_pages.max_response_bytes', $exact - 1);
    getJson('https://example.com' . $uri . '?limit=80')->assertStatus(413);
})->with(['navigation' => ['/api/v1/navigation'], 'media' => ['/api/v1/media']]);

it('limits the projected response, not the source it was read from', function (string $uri): void {
    config()->set('capell-api.public_pages.max_response_bytes', 1024);
    v1Page($this->site, $this->language, '/long', 'Long', '<p>Short</p><img src="/img/long.png" alt="Long"><script>' . str_repeat('x', 2000) . '</script>');

    getJson('https://example.com' . $uri)->assertOk()->assertJsonCount(1, 'data');
})->with(['pages' => ['/api/v1/pages'], 'navigation' => ['/api/v1/navigation'], 'media' => ['/api/v1/media']]);

it('never lets an unreturned lookahead page reject a fitting response', function (string $uri, string $lookahead): void {
    config()->set('capell-api.public_pages.max_response_bytes', 1024);
    config()->set('capell-api.public_pages.max_source_bytes', 4096);
    v1Page($this->site, $this->language, '/first', 'First');
    v1Page($this->site, $this->language, '/second', 'Second', str_repeat('<p>' . $lookahead . '</p>', 2000));

    $cursor = getJson('https://example.com' . $uri)->assertOk()->assertJsonCount(1, 'data')->json('meta.next_cursor');

    expect($cursor)->toBeString()->not->toBe('');
})->with([
    'over the response limit' => ['/api/v1/pages?limit=1', 'a'],
    'over the source ceiling' => ['/api/v1/pages?limit=1', str_repeat('b', 10)],
    'search over the source ceiling' => ['/api/v1/search?q=s&limit=1', str_repeat('c', 10)],
]);

it('rejects a returned page over the source ceiling on every projection without processing it', function (string $uri): void {
    config()->set('capell-api.public_pages.max_response_bytes', 1024);
    config()->set('capell-api.public_pages.max_source_bytes', 4096);
    v1Page($this->site, $this->language, '/huge', 'Huge', str_repeat('<p>a</p>', 2000));

    BuildPublicPagePayloadAction::mock()->shouldNotReceive('handle');

    getJson('https://example.com' . $uri)->assertStatus(413);
})->with([
    'pages' => ['/api/v1/pages'],
    'resolve' => ['/api/v1/pages/resolve?url=/huge'],
    'navigation' => ['/api/v1/navigation'],
    'media' => ['/api/v1/media'],
    'legacy resolve' => ['/api/capell/v1/pages/resolve?url=/huge'],
]);

it('keeps the source ceiling at or above the response limit so raising one never hides behind the other', function (): void {
    config()->set('capell-api.public_pages.max_response_bytes', 20000);
    config()->set('capell-api.public_pages.max_source_bytes', 2000);

    expect(ApiLimits::sourceBytes())->toBe(20000);
});
