<?php

declare(strict_types=1);

namespace Capell\Api\Support;

use Symfony\Component\HttpFoundation\Response;

final class ApiEdgeCacheHeaders
{
    /**
     * Cloudflare consumes and strips Cache-Tag before the visitor sees it; any
     * other edge forwards it unless it strips the header. Most edges forward
     * Surrogate-Key, so it is sent only when the deployment confirms its proxy
     * strips the header.
     *
     * @param  list<string>  $keys
     */
    public static function apply(Response $response, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $response->headers->set('Cache-Tag', implode(',', $keys));

        // Environment values arrive as strings ("true", "1"); only an explicit
        // true value enables the header.
        if (filter_var(config('capell-api.expose_surrogate_key_header', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true) {
            $response->headers->set('Surrogate-Key', implode(' ', $keys));
        }
    }
}
