<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware values wrap the public read-only Capell API routes. Keep
    | the defaults public and rate limited; consuming apps can add auth
    | middleware or replace the throttle middleware without replacing routes.
    |
    */
    'middleware' => ['api'],

    // Endpoint keys are stable queue references. Secrets never enter job payloads.
    // 'frontend' => ['site_id' => 1, 'url' => 'https://frontend.example/revalidate', 'secret' => env('CAPELL_API_WEBHOOK_SECRET')],
    'webhooks' => ['queue' => 'default', 'endpoints' => []],

    /*
    | Public responses always carry Cache-Tag with site-N and page-N purge keys.
    | Cloudflare consumes and strips it at the edge; any other edge forwards it
    | to visitors unless it strips the header. Surrogate-Key is not stripped by
    | most CDNs and would expose the same IDs, so it is only emitted when the
    | proxy in front of the origin removes it before the client.
    */
    'expose_surrogate_key_header' => Env::get('CAPELL_API_EXPOSE_SURROGATE_KEY', false),

    'public_pages' => [
        'auth_middleware' => null,
        'rate_limit_middleware' => 'throttle:capell-api',
        'rate_limit_per_minute' => 60,
        'max_candidate_sites' => 50,
        'max_layout_containers' => 12,
        'max_widgets_per_container' => 50,
        // Sanitised resources actually returned, including the response envelope.
        'max_response_bytes' => 1048576,
        // Raw source processed for a returned page; never below max_response_bytes.
        'max_source_bytes' => 8388608,
        'middleware' => [],
    ],
];
