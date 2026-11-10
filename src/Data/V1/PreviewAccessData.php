<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class PreviewAccessData extends Data
{
    public function __construct(
        public bool $enabled = false,
        public bool $restricted = false,
        public bool $deleted = false,
        public ?string $tokenId = null,
    ) {}
}
