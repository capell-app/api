<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class RevalidationWebhookData extends Data
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $surrogate_keys
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $event,
        public string $occurred_at,
        public array $paths,
        public array $surrogate_keys,
    ) {}
}
