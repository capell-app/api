<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class PageListData extends Data
{
    /** @param list<PageData> $data */
    public function __construct(public array $data, public ListMetaData $meta) {}
}
