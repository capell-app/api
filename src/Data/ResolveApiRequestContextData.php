<?php

declare(strict_types=1);

namespace Capell\Api\Data;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use LogicException;
use Spatie\LaravelData\Data;

final class ResolveApiRequestContextData extends Data
{
    public const string STATUS_OK = 'ok';

    public const string STATUS_NOT_FOUND = 'not_found';

    public const string STATUS_FORBIDDEN = 'forbidden';

    public function __construct(
        public string $status,
        public ?Site $site = null,
        public ?Language $language = null,
        public string $urlPath = '/',
    ) {}

    public static function ok(Site $site, Language $language, string $urlPath): self
    {
        return new self(
            status: self::STATUS_OK,
            site: $site,
            language: $language,
            urlPath: $urlPath,
        );
    }

    public static function notFound(): self
    {
        return new self(status: self::STATUS_NOT_FOUND);
    }

    public static function forbidden(): self
    {
        return new self(status: self::STATUS_FORBIDDEN);
    }

    public function found(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function isForbidden(): bool
    {
        return $this->status === self::STATUS_FORBIDDEN;
    }

    public function resolvedSite(): Site
    {
        if (! $this->site instanceof Site) {
            throw new LogicException('API request context has no resolved site.');
        }

        return $this->site;
    }

    public function resolvedLanguage(): Language
    {
        if (! $this->language instanceof Language) {
            throw new LogicException('API request context has no resolved language.');
        }

        return $this->language;
    }

    /**
     * @return list<string>
     */
    public function cacheTags(?Pageable $page = null): array
    {
        if (! $this->site instanceof Site || ! $this->language instanceof Language) {
            return ['api'];
        }

        $tags = [
            'api',
            'site:' . $this->cacheTagKey($this->site->getKey()),
            'language:' . $this->cacheTagKey($this->language->getKey()),
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
}
