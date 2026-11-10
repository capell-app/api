<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class PageData extends Data
{
    /** @param string|array<array-key, mixed>|null $content */
    public function __construct(
        public string $url,
        public ?string $title,
        public string|array|null $content,
    ) {}
}
