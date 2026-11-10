<?php

declare(strict_types=1);

namespace Capell\Api\Data\V1;

use Spatie\LaravelData\Data;

final class MediaData extends Data
{
    public function __construct(public string $url, public string $alt, public ?string $mime_type = null) {}
}
