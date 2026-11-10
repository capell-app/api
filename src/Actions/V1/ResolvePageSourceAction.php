<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Data\ResolveApiRequestContextData;
use Capell\Api\Data\V1\PreviewAccessData;
use Capell\Core\Actions\PublicRead\PageUrlIsPubliclyReadableAction;
use Capell\Core\Actions\ResolvePublicPageByUrlAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Data\PublicPageFieldsData;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static ?PublicPageFieldsData run(PageUrl $url, ResolveApiRequestContextData $context, PreviewAccessData $preview) */
final class ResolvePageSourceAction
{
    use AsFake;
    use AsObject;

    /**
     * Decides readability and loads raw fields. Nothing here is safe to return
     * until BuildPageDataAction has sanitised it.
     */
    public function handle(PageUrl $url, ResolveApiRequestContextData $context, PreviewAccessData $preview): ?PublicPageFieldsData
    {
        if ($context->siteDomain !== null) {
            $url->setRelation('siteDomain', $context->siteDomain);
        }
        if (! $preview->restricted && ! PageUrlIsPubliclyReadableAction::run($url)) {
            return null;
        }

        if ($preview->enabled) {
            $modelClass = Relation::getMorphedModel((string) $url->pageable_type) ?? $url->pageable_type;
            if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class) || ! is_subclass_of($modelClass, Pageable::class)) {
                return null;
            }
            $query = $modelClass::query()->where('site_id', $context->resolvedSite()->getKey());
            if ($preview->deleted) {
                $query->withTrashed();
            }
            $page = $query->whereKey($url->pageable_id)->first();
            if (! $page instanceof Pageable) {
                return null;
            }
            $translationQuery = $page->translations()->where('language_id', $context->resolvedLanguage()->getKey());
            if ($preview->deleted) {
                $translationQuery->withTrashed();
            }
            $translation = $translationQuery->first();
            if (! $translation instanceof Translation) {
                return null;
            }

            return PublicPageFieldsData::from(['url' => $url->url, 'title' => $translation->title, 'content' => $translation->content]);
        }

        $resolution = ResolvePublicPageByUrlAction::run($context->resolvedSite(), $context->resolvedLanguage(), $url->url, resolvedPageUrl: $url);

        return $resolution->found() ? $resolution->fields : null;
    }
}
