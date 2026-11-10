<?php

declare(strict_types=1);

namespace Capell\Api\Listeners;

use Capell\Api\Actions\V1\QueueRevalidationWebhooksAction;
use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Models\Contracts\Publishable;
use Capell\Core\Support\Publishing\PublishSentinel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class QueuePageRevalidation
{
    public function handle(PageSaved|PageDeleted $event): void
    {
        $page = $event->page;
        if (! $page instanceof Model || ! $page instanceof Publishable) {
            return;
        }
        $siteId = (int) $page->site_id;
        $keys = ['site-' . $siteId, 'page-' . $page->id];
        $kind = $event instanceof PageDeleted ? 'page.deleted'
            : ($page->isPending() || $page->isExpired() ? 'page.unpublished' : 'page.published');
        // Queue timed site purges too: ordinary publication dates are not contributor rules.
        if ($event instanceof PageSaved) {
            foreach (['visible_from' => 'page.published', 'visible_until' => 'page.unpublished'] as $attribute => $transition) {
                $at = $page->getAttribute($attribute);
                if ($at instanceof CarbonInterface && $at->isFuture() && ! PublishSentinel::isDraftValue($at)) {
                    $page->getConnection()->afterCommit(static function () use ($transition, $siteId, $keys, $at): void {
                        QueueRevalidationWebhooksAction::run($transition, $siteId, keys: $keys, at: $at);
                    });
                }
            }
        }
        // A site purge covers previous paths and URLs already removed by delete cascades.
        $page->getConnection()->afterCommit(static function () use ($kind, $siteId, $keys): void {
            QueueRevalidationWebhooksAction::run($kind, $siteId, keys: $keys);
        });
    }
}
