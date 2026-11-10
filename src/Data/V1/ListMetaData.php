<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class ListMetaData extends Data
{
    public function __construct(public ?string $next_cursor) {}
}
