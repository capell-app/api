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

    'public_pages' => [
        'auth_middleware' => null,
        'rate_limit_middleware' => 'throttle:capell-api',
        'rate_limit_per_minute' => 60,
        'max_candidate_sites' => 50,
        'max_layout_containers' => 12,
        'max_widgets_per_container' => 50,
        'max_response_bytes' => 1048576,
        'middleware' => [],
    ],
];
