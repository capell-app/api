<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Actions\BuildPublicPagePayloadAction;
use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Data\V1\PageData;
use Capell\Api\Support\ApiLimits;
use Capell\Core\Data\PublicPageFieldsData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static PageData run(string $url, PublicPageFieldsData $fields) */
final class BuildPageDataAction
{
    use AsFake;
    use AsObject;

    public function handle(string $url, PublicPageFieldsData $fields): PageData
    {
        abort_if(ApiLimits::sourceExceeded($fields), 413, __('capell-api::api.too_large'));
        $safe = BuildPublicPagePayloadAction::run($fields, new PublicPagePayloadOptionsData);
        $content = $safe['content'] ?? null;

        return new PageData(
            url: $url,
            title: is_string($safe['title'] ?? null) ? $safe['title'] : null,
            content: is_string($content) || is_array($content) ? $content : null,
        );
    }
}
