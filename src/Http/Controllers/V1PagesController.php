<?php

declare(strict_types=1);

namespace Capell\Api\Http\Controllers;

use Capell\Api\Actions\ResolveApiRequestContextAction;
use Capell\Api\Actions\V1\ListPagesAction;
use Capell\Api\Actions\V1\ResolvePreviewAccessAction;
use Capell\Api\Data\V1\ErrorData;
use Capell\Api\Data\V1\PageListData;
use Capell\Api\Data\V1\PageResponseData;
use Capell\Api\Support\ApiEdgeCacheHeaders;
use Capell\Api\Support\ApiLimits;
use Capell\Core\Support\PublicRead\PublicReadProjectionHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class V1PagesController
{
    public function __invoke(Request $request, ?string $type = null): JsonResponse
    {
        try {
            $context = ResolveApiRequestContextAction::run($request);
            abort_if($context->isForbidden(), 403, __('capell-api::api.forbidden'));
            abort_unless($context->found(), 404, __('capell-api::api.not_found'));
            $preview = ResolvePreviewAccessAction::run($request, $context->resolvedSite());
            $single = $request->routeIs('capell-api.v1.resolve');
            $projection = match (true) {
                $request->routeIs('capell-api.v1.navigation') => 'navigation',
                $request->routeIs('capell-api.v1.media') => 'media',
                default => null,
            };
            $list = ListPagesAction::run($request, $context, $preview, $type, $single, $projection);
            abort_if($single && $list->data === [], 404, __('capell-api::api.not_found'));
            $first = $list instanceof PageListData ? ($list->data[0] ?? null) : null;
            $payload = $single && $first !== null ? new PageResponseData($first) : $list;
            $response = response()->json($payload->toArray())->header('X-Capell-Api-Version', 'v1');
            $content = (string) $response->getContent();
            abort_if(strlen($content) > ApiLimits::responseBytes(), 413, __('capell-api::api.too_large'));
            if ($preview->enabled || $request->hasHeader('Authorization')) {
                return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
            }
            ApiEdgeCacheHeaders::apply($response, ['site-' . $context->resolvedSite()->id]);
            $response->header('Vary', 'Authorization');
            PublicReadProjectionHeaders::apply($response, $request, $content);

            return $response;
        } catch (ValidationException) {
            return $this->error(__('capell-api::api.invalid_query'), 422);
        } catch (HttpExceptionInterface $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json((new ErrorData($message))->toArray(), $status)
            ->header('X-Capell-Api-Version', 'v1')->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
