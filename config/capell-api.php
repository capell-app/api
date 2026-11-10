<?php

declare(strict_types=1);

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
