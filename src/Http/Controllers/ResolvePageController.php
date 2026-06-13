<?php

declare(strict_types=1);

namespace Capell\Api\Http\Controllers;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Providers\ApiServiceProvider;
use Capell\Core\Actions\LoadSiteDomainFromUrlAction;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\ExtensionStatusEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\CapellExtension;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

final class ResolvePageController
{
    private const string API_VERSION = 'v1';

    private ?SiteDomain $resolvedSiteDomain = null;

    private ?string $resolvedUrlPath = null;

    /** @var list<string> */
    private array $cacheTags = ['api'];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->packageIsInstalled()) {
            return $this->notFound();
        }

        $site = $this->resolveSite($request);

        if ($site instanceof JsonResponse) {
            return $site;
        }

        if (! $site instanceof Site) {
            return $this->notFound();
        }

        if ($this->hasExplicitLanguage($request) && ! $this->hasValidContextSignature($request)) {
            return $this->forbidden();
        }

        $language = $this->resolveLanguage($site, $request->query('language'));

        if (! $language instanceof Language) {
            return $this->notFound();
        }

        $resolution = ResolvePublicPageByUrlAction::run(
            site: $site,
            language: $language,
            url: $this->resolvedUrlPath ?? $this->queryString($request, 'url', '/'),
        );

        if (! $resolution->found()) {
            return $this->notFound();
        }

        $options = PublicPagePayloadOptionsData::fromRequest($request);

        if ($options->shouldIncludeLayout() && $resolution->layout instanceof Layout && $resolution->page instanceof Page && $options->requestsUnboundedLayoutHtml()) {
            return $this->badRequest('layout.html requires explicit bounded containers.');
        }

        $this->cacheTags = $this->cacheTags($site, $language, $resolution->page);
        $data = BuildPublicPagePayloadAction::run(
            fields: $resolution->fields,
            options: $options,
            layout: $resolution->layout,
            page: $resolution->page instanceof Page ? $resolution->page : null,
            language: $language,
        );

        return $this->json(['data' => $data]);
    }

    private function notFound(): JsonResponse
    {
        return $this->json(['message' => 'Page not found'], 404);
    }

    private function forbidden(): JsonResponse
    {
        return $this->json(['message' => 'Forbidden'], 403);
    }

    private function badRequest(string $message): JsonResponse
    {
        return $this->json(['message' => $message], 422);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('X-Capell-Api-Version', self::API_VERSION)
            ->header('X-Capell-Cache-Tags', implode(',', $this->cacheTags));
    }

    /**
     * @return list<string>
     */
    private function cacheTags(Site $site, Language $language, ?Pageable $page): array
    {
        $tags = [
            'api',
            'site:' . $this->cacheTagKey($site->getKey()),
            'language:' . $this->cacheTagKey($language->getKey()),
        ];

        if ($page instanceof Pageable) {
            $tags[] = 'page:' . $this->cacheTagKey($page->getKey());
        }

        return $tags;
    }

    private function cacheTagKey(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }

    private function packageIsInstalled(): bool
    {
        if (CapellCore::isPackageInstalled(ApiServiceProvider::$packageName)) {
            return true;
        }

        try {
            return CapellExtension::query()
                ->where('composer_name', ApiServiceProvider::$packageName)
                ->where('status', ExtensionStatusEnum::Enabled)
                ->where('marketplace_runtime_allowed', true)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function resolveSite(Request $request): Site|JsonResponse|null
    {
        $site = is_string($request->query('site')) ? trim($request->query('site')) : '';

        if ($site !== '') {
            if (! $this->hasValidContextSignature($request)) {
                return $this->forbidden();
            }

            return Site::query()->whereKey($site)->first();
        }

        $publicPageUrl = $this->publicPageUrl($request);
        $resolved = LoadSiteDomainFromUrlAction::run($publicPageUrl, $this->candidateSitesForUrl($publicPageUrl));
        $siteDomain = is_array($resolved) ? ($resolved[0] ?? null) : null;
        $urlPath = is_array($resolved) ? ($resolved[1] ?? null) : null;

        if (! $siteDomain instanceof SiteDomain) {
            return null;
        }

        $this->resolvedSiteDomain = $siteDomain;
        $this->resolvedUrlPath = is_string($urlPath) && $urlPath !== '' ? $urlPath : null;

        return $siteDomain->site;
    }

    private function hasExplicitLanguage(Request $request): bool
    {
        $language = $request->query('language');

        return is_string($language) && trim($language) !== '';
    }

    private function hasValidContextSignature(Request $request): bool
    {
        return $request->hasValidSignatureWhileIgnoring(['fields', 'include', 'containers']);
    }

    private function resolveLanguage(Site $site, mixed $language): ?Language
    {
        $language = is_scalar($language) ? trim((string) $language) : '';

        if ($language !== '') {
            $languageQuery = Language::query()->whereIn((new Language)->getKeyName(), $this->siteLanguageIds($site));
            $byId = (clone $languageQuery)->whereKey($language)->first();

            if ($byId instanceof Language) {
                return $byId;
            }

            return $languageQuery
                ->where(function (BuilderContract $query) use ($language): void {
                    $query->where('code', $language)
                        ->orWhere('locale', $language);
                })
                ->first();
        }

        $domainLanguage = $this->resolvedSiteDomain?->language;

        if ($domainLanguage instanceof Language) {
            return $domainLanguage;
        }

        $siteLanguageId = $site->getAttribute('language_id');

        if (is_int($siteLanguageId)) {
            $siteLanguage = $site->relationLoaded('language')
                ? $site->language
                : Language::query()->whereKey($siteLanguageId)->first();

            if ($siteLanguage instanceof Language) {
                return $siteLanguage;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function siteLanguageIds(Site $site): array
    {
        $languageIds = collect([$site->getAttribute('language_id')])
            ->merge($site->siteDomains()->pluck('language_id'));

        return array_values($languageIds
            ->filter(fn (mixed $languageId): bool => is_int($languageId) || (is_string($languageId) && ctype_digit($languageId)))
            ->map(fn (mixed $languageId): int => (int) $languageId)
            ->unique()
            ->values()
            ->all());
    }

    private function queryString(Request $request, string $key, string $default): string
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    private function publicPageUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($this->queryString($request, 'url', '/'), '/');
    }

    /**
     * @return Collection<int, Site>
     */
    private function candidateSitesForUrl(string $url): Collection
    {
        $parts = parse_url($url);
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : null;
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $exactHostSites = $this->candidateSitesForDomain($host, $scheme, includeWildcardDomains: true);

        if ($exactHostSites->isNotEmpty()) {
            return $exactHostSites;
        }

        return $this->candidateSitesForDomain(null, $scheme, includeWildcardDomains: false);
    }

    /**
     * @return Collection<int, Site>
     */
    private function candidateSitesForDomain(?string $host, string $scheme, bool $includeWildcardDomains): Collection
    {
        return Site::query()
            ->with([
                'language',
                'siteDomains' => fn (BuilderContract $query): BuilderContract => $this->candidateSiteDomainQuery($query, $host, $scheme, includeWildcardDomains: $includeWildcardDomains)
                    ->with('language'),
            ])
            ->whereHas('siteDomains', fn (BuilderContract $query): BuilderContract => $this->candidateSiteDomainQuery($query, $host, $scheme, includeWildcardDomains: false))
            ->limit($this->candidateSiteLimit())
            ->get();
    }

    private function candidateSiteLimit(): int
    {
        $limit = config('capell-api.public_pages.max_candidate_sites', 50);

        if (is_int($limit)) {
            return $limit > 0 ? $limit : 50;
        }

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : 50;
    }

    private function candidateSiteDomainQuery(BuilderContract $query, ?string $host, string $scheme, bool $includeWildcardDomains): BuilderContract
    {
        return $query
            ->where(function (BuilderContract $query) use ($host, $includeWildcardDomains): void {
                if ($host === null) {
                    $query->whereNull('domain');

                    return;
                }

                $query->where('domain', $host);

                if ($includeWildcardDomains) {
                    $query->orWhereNull('domain');
                }
            })
            ->where(function (BuilderContract $query) use ($scheme): void {
                $query
                    ->whereNull('scheme')
                    ->orWhere('scheme', false)
                    ->orWhere('scheme', $scheme);
            });
    }
}
