<?php

declare(strict_types=1);

namespace Capell\Api\Data;

use Spatie\LaravelData\Data;

/** Compatibility envelope for the existing selectable page/layout projection. */
final class LegacyPageResponseData extends Data
{
    /** @param array<array-key, mixed> $data */
    public function __construct(public array $data) {}
}
