<?php

declare(strict_types=1);

namespace Capell\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponsePolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $private = $request->hasHeader('Authorization') || $request->query('preview', '0') !== '0';
        if ($private) {
            // Core's outer public-read middleware also evaluates conditional headers.
            $request->headers->remove('If-None-Match');
            $request->headers->remove('If-Modified-Since');
        }
        /** @var Response $response */
        $response = $next($request);
        if ($private || $response->getStatusCode() >= 400) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->remove('ETag');
            $response->headers->remove('Last-Modified');
            $response->headers->remove('Surrogate-Key');
            $response->headers->remove('Cache-Tag');
        }
        $response->setVary('Authorization', false);

        return $response;
    }
}
