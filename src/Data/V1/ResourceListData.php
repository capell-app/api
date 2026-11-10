<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class ResourceListData extends Data
{
    /** @param list<NavigationLinkData|MediaData> $data */
    public function __construct(public array $data, public ListMetaData $meta) {}
}
