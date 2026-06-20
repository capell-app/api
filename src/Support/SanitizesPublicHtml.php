<?php

declare(strict_types=1);

namespace Capell\Api\Support;

use Capell\Core\Support\Security\PublicHtmlSanitizer;

trait SanitizesPublicHtml
{
    private function sanitizeHtmlValue(mixed $value): mixed
    {
        return resolve(PublicHtmlSanitizer::class)->sanitizePublicValue($value);
    }
}
