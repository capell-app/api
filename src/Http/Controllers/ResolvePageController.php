<?php

declare(strict_types=1);

namespace Capell\Api\Http\Controllers;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Actions\ResolveApiRequestContextAction;
use Capell\Api\Actions\ValidatePublicLayoutPayloadBoundsAction;
use Capell\Api\Data\LegacyPageResponseData;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Data\V1\ErrorData;
use Capell\Api\Support\ApiLimits;
use Capell\Core\Actions\PublicRead\PageUrlIsPubliclyReadableAction;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Support\PublicRead\PublicReadProjectionHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ResolvePageController
{
    private const string API_VERSION = 'v1';

    /** @var list<string> */
    private array $cacheTags = ['api'];

    public function __invoke(Request $request): JsonResponse
    {
        $context = ResolveApiRequestContextAction::run($request);

        if ($context->isForbidden()) {
            return $this->forbidden();
        }

        if (! $context->found()) {
            return $this->notFound();
        }

        $resolution = ResolvePublicPageByUrlAction::run(
            site: $context->resolvedSite(),
            language: $context->resolvedLanguage(),
            url: $context->urlPath,
        );

        if (! $resolution->found()) {
            return $this->notFound();
        }

        $pageUrl = $resolution->page?->getRelation('pageUrl');
        if ($pageUrl instanceof PageUrl && $context->siteDomain !== null) {
            $pageUrl->setRelation('siteDomain', $context->siteDomain);
        }
        if (! $pageUrl instanceof PageUrl || ! PageUrlIsPubliclyReadableAction::run($pageUrl)) {
            return $this->notFound();
        }

        $options = PublicPagePayloadOptionsData::fromRequest($request);

        if ($options->shouldIncludeLayout() && $resolution->layout instanceof Layout && $resolution->page instanceof Page && $options->requestsUnboundedLayoutHtml()) {
            return $this->badRequest('layout.html requires explicit bounded containers.');
        }

        if ($options->shouldIncludeLayout() && $resolution->layout instanceof Layout) {
            $boundsError = ValidatePublicLayoutPayloadBoundsAction::run($resolution->layout, $options);

            if (is_string($boundsError)) {
                return $this->badRequest($boundsError);
            }
        }

        if (ApiLimits::sourceExceeded($resolution->fields)) {
            return $this->json(['message' => 'API response exceeds the configured byte limit.'], 413);
        }

        $this->cacheTags = $context->cacheTags($resolution->page);
        $data = BuildPublicPagePayloadAction::run(
            fields: $resolution->fields,
            options: $options,
            layout: $resolution->layout,
            page: $resolution->page instanceof Page ? $resolution->page : null,
            language: $context->resolvedLanguage(),
        );

        return $this->jsonWithEtag(['data' => $data], $request);
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
        $body = isset($payload['data']) && is_array($payload['data'])
            ? new LegacyPageResponseData($payload['data'])
            : new ErrorData(is_string($payload['message'] ?? null) ? $payload['message'] : '');
        $response = response()
            ->json($body->toArray(), $status)
            ->header('Cache-Control', $status === 200 ? PublicReadProjectionHeaders::cacheControl() : 'private, no-store')
            ->header('Deprecation', '@1793923200')
            ->header('Link', '</api/v1/pages/resolve>; rel="successor-version"')
            ->header('X-Capell-Api-Version', self::API_VERSION)
            ->header('X-Capell-Cache-Tags', implode(',', $this->cacheTags));
        if ($status === 200) {
            $keys = array_map(static fn (string $tag): string => str_replace(':', '-', $tag), array_values(array_filter(
                $this->cacheTags,
                static fn (string $tag): bool => str_starts_with($tag, 'site:') || str_starts_with($tag, 'page:'),
            )));
            $response->header('Surrogate-Key', implode(' ', $keys))->header('Cache-Tag', implode(',', $keys));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonWithEtag(array $payload, Request $request): JsonResponse
    {
        $response = $this->json($payload);
        $responseContent = $response->getContent();
        throw_unless(is_string($responseContent), LogicException::class, 'JSON responses must contain serialized content.');

        if (strlen($responseContent) > ApiLimits::responseBytes()) {
            return $this->json(['message' => 'API response exceeds the configured byte limit.'], 413);
        }

        // Validate the bytes actually sent, including current public-output policy.
        if (! $request->hasHeader('Authorization')) {
            PublicReadProjectionHeaders::apply($response, $request, $responseContent);
        }

        return $response;
    }
}
