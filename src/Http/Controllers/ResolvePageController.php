<?php

declare(strict_types=1);

namespace Capell\Api\Http\Controllers;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Actions\ResolveApiRequestContextAction;
use Capell\Api\Actions\ValidatePublicLayoutPayloadBoundsAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
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
        return response()
            ->json($payload, $status)
            ->header('X-Capell-Api-Version', self::API_VERSION)
            ->header('X-Capell-Cache-Tags', implode(',', $this->cacheTags));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonWithEtag(array $payload, Request $request): JsonResponse
    {
        $response = $this->json($payload);
        $responseContent = $response->getContent();
        throw_unless(is_string($responseContent), LogicException::class, 'JSON responses must contain serialized content.');
        $configuredMaxResponseBytes = config('capell-api.public_pages.max_response_bytes', 1048576);
        $maxResponseBytes = max(1024, is_int($configuredMaxResponseBytes) ? $configuredMaxResponseBytes : 1048576);

        if (strlen($responseContent) > $maxResponseBytes) {
            return $this->json(['message' => 'API response exceeds the configured byte limit.'], 413);
        }

        // Validate the bytes actually sent, including current public-output policy.
        $response->setEtag(hash('sha256', $responseContent));
        $response->isNotModified($request);

        return $response;
    }
}
