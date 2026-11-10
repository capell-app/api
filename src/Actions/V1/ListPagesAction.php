<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Data\ResolveApiRequestContextData;
use Capell\Api\Data\V1\ListMetaData;
use Capell\Api\Data\V1\MediaData;
use Capell\Api\Data\V1\NavigationLinkData;
use Capell\Api\Data\V1\PageData;
use Capell\Api\Data\V1\PageListData;
use Capell\Api\Data\V1\PreviewAccessData;
use Capell\Api\Data\V1\ResourceListData;
use Capell\Api\Support\ApiLimits;
use Capell\Core\Actions\ResolvePublicPageableMorphTypesAction;
use Capell\Core\Data\PublicPageFieldsData;
use Capell\Core\Enums\UrlTypeEnum;
use Capell\Core\Models\PageUrl;
use Capell\Core\Support\PublicRead\PublicReadCursor;
use Capell\Core\Support\PublicRead\PublicReadScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static PageListData|ResourceListData run(Request $request, ResolveApiRequestContextData $context, PreviewAccessData $preview, ?string $type = null, bool $single = false, ?string $projection = null) */
final class ListPagesAction
{
    use AsFake;
    use AsObject;

    public function handle(Request $request, ResolveApiRequestContextData $context, PreviewAccessData $preview, ?string $type = null, bool $single = false, ?string $projection = null): PageListData|ResourceListData
    {
        $input = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'filter' => ['sometimes', 'array:type,path'],
            'filter.type' => ['sometimes', 'string', 'max:100'],
            'filter.path' => ['sometimes', 'string', 'max:1024'],
            'url' => ['sometimes', 'string', 'max:1024', 'starts_with:/'],
            'q' => [$request->routeIs('capell-api.v1.search') ? 'required' : 'sometimes', 'string', 'min:1', 'max:200'],
        ]);
        $filter = $input['filter'] ?? [];
        $path = $single ? $context->urlPath : ($filter['path'] ?? null);
        $type ??= $filter['type'] ?? null;
        $size = $single ? 1 : (int) ($input['limit'] ?? 20);
        $search = $input['q'] ?? null;
        $fingerprint = PublicReadCursor::context('api.v1.pages', [
            $request->path(), $request->getSchemeAndHttpHost(),
            $context->resolvedSite()->getKey(), $context->resolvedLanguage()->getKey(), $context->siteDomain?->getKey(),
            $type, $path, $search, $size, $preview->toArray(), $projection,
        ]);
        $token = $input['cursor'] ?? null;
        $cursor = PublicReadCursor::open($token, $fingerprint);
        abort_if($token !== null && $cursor === null, 422, __('capell-api::api.invalid_cursor'));

        $morphTypes = ResolvePublicPageableMorphTypesAction::run();
        $query = PageUrl::query()
            ->where('site_id', $context->resolvedSite()->getKey())
            ->where('language_id', $context->resolvedLanguage()->getKey())
            ->whereIn('pageable_type', $morphTypes)
            ->where(fn (Builder $query): Builder => $query->whereNull('type')->orWhere('type', '!=', UrlTypeEnum::Redirect))
            ->enabled();
        if ($preview->deleted) {
            $query->withTrashed();
        }
        if (is_string($path)) {
            $query->where('url', '/' . trim($path, '/'));
        }
        // Only filter by the registered public blueprint key, never a caller-supplied column.
        $query->whereHasMorph('pageable', $morphTypes, function (Builder $query) use ($type, $preview): void {
            if ($preview->deleted) {
                $query->withTrashed();
            }
            $query->whereHas('blueprint', function (Builder $query) use ($type, $preview): void {
                if (! $preview->restricted) {
                    $query->enabled()->accessible();
                }
                if (is_string($type)) {
                    $query->where('key', $type);
                }
            });
            if (! $preview->enabled) {
                $query->publishedDate();
            }
        });

        /**
         * Each entry is the projected response resource for one returned page, so the
         * byte limit and the memory held are bounded by what is returned.
         *
         * @var array<int, PageData|list<NavigationLinkData|MediaData>> $payloads
         */
        $payloads = [];
        $bytes = 0;
        $returnedAny = false;
        $maxBytes = ApiLimits::responseBytes();
        $lookaheadFound = false;
        $scan = (new PublicReadScanner)->scan(
            fetch: static function (?array $after, int $limit) use ($query, $size): array {
                $limit = min($limit, $size + 1);
                $batch = (clone $query)->when($after !== null, fn (Builder $query): Builder => $query->where('id', '>', $after['id'] ?? 0))
                    ->orderBy('id')->limit($limit)->with('siteDomain')->get();
                $positions = $batch->map(static fn (PageUrl $url): array => ['id' => $url->id])->all();

                return ['items' => array_values($batch->all()), 'positions' => array_values($positions), 'after' => $positions === [] ? $after : $positions[array_key_last($positions)], 'more' => $batch->count() === $limit];
            },
            readable: static function (array $urls) use ($context, $preview, $search, $size, $maxBytes, $projection, &$bytes, &$returnedAny, &$payloads, &$lookaheadFound): array {
                return array_map(static function (PageUrl $url) use ($context, $preview, $search, $size, $maxBytes, $projection, &$bytes, &$returnedAny, &$payloads, &$lookaheadFound): bool {
                    if ($lookaheadFound) {
                        return false;
                    }
                    $fields = ResolvePageSourceAction::run($url, $context, $preview);
                    if ($fields === null || ! self::matchesSearch($fields, $search)) {
                        return false;
                    }
                    // This page only proves another page exists: it is never processed, measured or returned.
                    if (count($payloads) >= $size) {
                        $lookaheadFound = true;

                        return true;
                    }
                    $data = BuildPageDataAction::run($url->url, $fields);
                    $projected = $projection === null ? $data : ProjectPageResourcesAction::run($data, $projection === 'media');
                    // Count the bytes the response will carry: each returned resource, plus the one comma
                    // joining it to the previous one. A page's own list brackets are never emitted (its items
                    // are flattened into the response), and a page projecting nothing adds nothing.
                    foreach (is_array($projected) ? $projected : [$projected] as $item) {
                        $bytes += strlen(json_encode($item->toArray(), JSON_THROW_ON_ERROR)) + ($returnedAny ? 1 : 0);
                        $returnedAny = true;
                    }
                    abort_if($bytes > $maxBytes, 413, __('capell-api::api.too_large'));
                    $payloads[$url->id] = $projected;

                    return true;
                }, $urls);
            },
            size: $size,
            page: 1,
            cursor: $cursor,
        );
        $next = $scan->next?->seal($fingerprint);
        abort_if($scan->hasMore && $next === null, 503, __('capell-api::api.unavailable'));

        if ($projection === null) {
            $pages = [];
            foreach ($scan->items as $url) {
                $page = $payloads[$url->id];
                if ($page instanceof PageData) {
                    $pages[] = $page;
                }
            }

            return new PageListData($pages, new ListMetaData($next));
        }

        $resources = [];
        foreach ($scan->items as $url) {
            $projected = $payloads[$url->id];
            if (is_array($projected)) {
                array_push($resources, ...$projected);
            }
        }

        return new ResourceListData($resources, new ListMetaData($next));
    }

    /** Search compares the sanitised title, the only part of a page it may reveal. */
    private static function matchesSearch(PublicPageFieldsData $fields, ?string $search): bool
    {
        if ($search === null) {
            return true;
        }

        $safe = BuildPublicPagePayloadAction::run($fields, new PublicPagePayloadOptionsData(fields: ['title']));
        $title = is_string($safe['title'] ?? null) ? $safe['title'] : '';

        return str_contains(mb_strtolower($title), mb_strtolower($search));
    }
}
