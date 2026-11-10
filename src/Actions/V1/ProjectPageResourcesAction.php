<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Data\V1\MediaData;
use Capell\Api\Data\V1\NavigationLinkData;
use Capell\Api\Data\V1\PageData;
use DOMDocument;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static list<NavigationLinkData|MediaData> run(PageData $page, bool $media) */
final class ProjectPageResourcesAction
{
    use AsFake;
    use AsObject;

    /** @return list<NavigationLinkData|MediaData> */
    public function handle(PageData $page, bool $media): array
    {
        if (! $media) {
            return [new NavigationLinkData($page->url, $page->title)];
        }

        // Only expose images already present in sanitised readable content.
        // Enumerating attachments would accidentally mint private storage URLs.
        if (! is_string($page->content) || $page->content === '') {
            return [];
        }

        $items = [];
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $page->content, LIBXML_NONET);
            foreach ($document->getElementsByTagName('img') as $image) {
                $src = $image->getAttribute('src');
                // Signed URLs and data URLs are not public asset identities.
                if ($src === '' || str_contains($src, '?') || str_contains($src, '#')
                    || (! str_starts_with($src, '/') && ! str_starts_with($src, 'https://'))
                    || str_starts_with($src, '//')) {
                    continue;
                }
                $items[] = new MediaData($src, $image->getAttribute('alt'));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $items;
    }
}
