<?php

declare(strict_types=1);

namespace Capell\Api\Actions\V1;

use Capell\Api\Data\V1\PreviewAccessData;
use Capell\Core\Models\Site;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static PreviewAccessData run(Request $request, Site $site) */
final class ResolvePreviewAccessAction
{
    use AsFake;
    use AsObject;

    public function handle(Request $request, Site $site): PreviewAccessData
    {
        abort_unless(in_array($request->query('preview', '0'), ['0', '1'], true), 422, __('capell-api::api.invalid_query'));
        if ($request->query('preview') !== '1') {
            return new PreviewAccessData;
        }

        $bearer = $request->bearerToken();
        abort_unless(is_string($bearer) && $bearer !== '' && strlen($bearer) <= 512, 403, __('capell-api::api.forbidden'));
        // Honour Sanctum's configured model, provider and token validation callback.
        // A session-authenticated SPA receives a transient token and is rejected here.
        $user = $request->user('sanctum');
        $token = is_object($user) && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        abort_unless($token instanceof PersonalAccessToken
            // capell-permission-scan: token-ability Sanctum token scope, not a role permission
            && $token->can('api:preview')
            && $token->can('api:preview:site:' . $site->id), 403, __('capell-api::api.forbidden'));

        $tokenId = $token->getKey();
        abort_unless(is_string($tokenId) || is_int($tokenId), 403, __('capell-api::api.forbidden'));

        return new PreviewAccessData(
            enabled: true,
            // capell-permission-scan: token-ability Sanctum token scope, not a role permission
            restricted: $token->can('api:preview:restricted'),
            // capell-permission-scan: token-ability Sanctum token scope, not a role permission
            deleted: $token->can('api:preview:deleted'),
            tokenId: (string) $tokenId,
        );
    }
}
