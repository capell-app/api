<?php

declare(strict_types=1);

namespace Capell\Api\Actions;

use Capell\Api\Data\PublicPagePayloadOptionsData;
use Capell\Api\Support\SanitizesPublicHtml;
use Capell\Core\Data\PublicPageFieldsData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class BuildPublicPagePayloadAction
{
    use AsFake;
    use AsObject;
    use SanitizesPublicHtml;

    private const array DEFAULT_FIELDS = ['url', 'title', 'content'];

    private const array ALLOWED_FIELDS = ['url', 'title', 'content', 'meta'];

    /**
     * Translation meta also holds editor, import and rendering state, so only
     * the search fields an editor writes for the public page leave the server.
     */
    private const array PUBLIC_META_KEYS = ['title', 'description', 'keywords'];

    /**
     * @return array<string, mixed>
     */
    public function handle(
        PublicPageFieldsData $fields,
        PublicPagePayloadOptionsData $options,
        ?Layout $layout = null,
        ?Page $page = null,
        ?Language $language = null,
    ): array {
        $data = $this->fields($fields, $options);

        if ($options->shouldIncludeLayout() && $layout instanceof Layout && $page instanceof Page && $language instanceof Language) {
            $data['layout'] = BuildPublicLayoutPayloadAction::run(
                layout: $layout,
                page: $page,
                language: $language,
                options: $options,
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(PublicPageFieldsData $fields, PublicPagePayloadOptionsData $options): array
    {
        $selectedFields = $options->fields === []
            ? self::DEFAULT_FIELDS
            : array_values(array_intersect($options->fields, self::ALLOWED_FIELDS));

        $data = [];

        foreach ($selectedFields as $field) {
            $value = match ($field) {
                'url' => $fields->url,
                'title' => $fields->title,
                'content' => $fields->content,
                'meta' => $this->publicMeta($fields->meta),
                default => null,
            };

            $data[$field] = $this->sanitizeHtmlValue($value);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function publicMeta(array $meta): array
    {
        $public = [];

        foreach (self::PUBLIC_META_KEYS as $key) {
            if (is_string($meta[$key] ?? null)) {
                $public[$key] = $meta[$key];
            }
        }

        return $public;
    }
}
