<?php

declare(strict_types=1);

namespace Capell\Api\Http\Controllers;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Actions\ResolveApiRequestContextAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $options = PublicPagePayloadOptionsData::fromRequest($request);

        if ($options->shouldIncludeLayout() && $resolution->layout instanceof Layout && $resolution->page instanceof Page && $options->requestsUnboundedLayoutHtml()) {
            return $this->badRequest('layout.html requires explicit bounded containers.');
        }

        $this->cacheTags = $context->cacheTags($resolution->page);
        $data = BuildPublicPagePayloadAction::run(
            fields: $resolution->fields,
            options: $options,
            layout: $resolution->layout,
            page: $resolution->page instanceof Page ? $resolution->page : null,
            language: $context->resolvedLanguage(),
        );

        return $this->jsonWithEtag($request, ['data' => $data]);
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
     * @param  array<string, mixed>  $payload
     */
    private function jsonWithEtag(Request $request, array $payload): JsonResponse
    {
        $response = $this->json($payload);
        $responseContent = $response->getContent();

        $response->setEtag(hash('sha256', is_string($responseContent) ? $responseContent : ''));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
