<?php

declare(strict_types=1);

return [
    'headers' => [
        'failed' => 'The API resolve route did not emit the expected version and cache-tag headers.',
        'label' => 'API response contract headers',
        'passed' => 'The API resolve route emits version and cache-tag headers.',
        'remediation' => 'Ensure the route still resolves through ResolvePageController and every JSON response includes X-Capell-Api-Version and X-Capell-Cache-Tags.',
    ],
    'middleware' => [
        'failed' => 'The API middleware configuration contains invalid values: :failures.',
        'failure' => [
            'invalid_value' => ':key must be null, false, a middleware string, or a list of middleware strings.',
            'missing_route_middleware' => 'The v1 resolve route is missing configured middleware: :middleware.',
        ],
        'label' => 'API middleware configuration',
        'passed' => 'The API middleware configuration can be safely published by the host app.',
        'remediation' => 'Update config/capell-api.php so each middleware option is null, false, a middleware string, or a list of middleware strings.',
    ],
    'route' => [
        'failed' => 'The API v1 page resolve route is not usable: :failures.',
        'failure' => [
            'controller' => 'The route does not point at :controller.',
            'method' => 'The route does not accept GET requests.',
            'missing' => 'The named route :route is missing.',
            'not_installed' => ':package is not marked as installed.',
            'uri' => 'The route URI is not :uri.',
        ],
        'label' => 'API v1 page resolve route',
        'passed' => 'The API v1 page resolve route is registered and points at the resolve controller.',
        'remediation' => 'Ensure ApiServiceProvider boots while capell-app/api is installed and its package routes are loaded.',
    ],
];
