<?php

declare(strict_types=1);

namespace Capell\Api\Support;

use Capell\Core\Data\PublicPageFieldsData;

/**
 * Two separate limits. The source ceiling bounds what the API will process for a
 * page it is about to return. The response limit applies only to the sanitised
 * resources actually returned, never to raw fields or an unreturned page.
 */
final class ApiLimits
{
    public static function responseBytes(): int
    {
        $configured = config('capell-api.public_pages.max_response_bytes', 1048576);

        return max(1024, is_int($configured) ? $configured : 1048576);
    }

    public static function sourceBytes(): int
    {
        $configured = config('capell-api.public_pages.max_source_bytes', 8388608);

        return max(self::responseBytes(), is_int($configured) ? $configured : 8388608);
    }

    public static function sourceExceeded(PublicPageFieldsData $fields): bool
    {
        $content = is_string($fields->content) ? $fields->content : json_encode($fields->content, JSON_THROW_ON_ERROR);

        return strlen((string) $fields->title) + strlen($content) > self::sourceBytes();
    }
}
