<?php

declare(strict_types=1);

return [
    'webhook_health_label' => 'Headless revalidation webhook configuration',
    'webhook_health_passed' => 'Webhook endpoints and queue configuration are ready.',
    'webhook_health_failed' => 'Webhook endpoints or the asynchronous queue are not configured correctly.',
    'webhook_health_remediation' => 'Set a site, HTTPS URL and independent secret of at least 32 characters for each endpoint, and supervise an asynchronous queue worker.',
    'not_found' => 'Page not found.',
    'forbidden' => 'This token cannot preview this content.',
    'invalid_query' => 'Check the API query parameters and try again.',
    'invalid_cursor' => 'This cursor has expired or belongs to a different request. Start again without a cursor.',
    'unavailable' => 'Content eligibility is temporarily unavailable. Try again shortly.',
    'too_large' => 'The API response exceeds the configured byte limit.',
];
