<?php

namespace App\Http\Middleware;

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Authorisation\Enums\SettingsPermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets the request through with a bearer tenant API token that has the given ability, and
 * hasn't expired or been revoked. The request then acts as the user who created the token.
 *
 * A token stops working when its user can no longer manage API tokens, so it never does more
 * than they could. Responds with a 401 for a missing or invalid token, and a 403 for one
 * without the ability.
 *
 * Usage: Route::middleware(['auth.tenant-token:dj:sync', 'throttle:tenant-api-token'])
 */
class AuthenticateTenantToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $ability = ApiTokenAbility::from($ability);

        $user = Auth::guard('sanctum')->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        abort_unless(
            $user instanceof User && $token instanceof ApiToken && $user->can(SettingsPermission::ManageApiTokens),
            Response::HTTP_UNAUTHORIZED,
            __('site_settings.api_tokens.api.unauthenticated'),
        );

        abort_unless(
            $token->can($ability->value),
            Response::HTTP_FORBIDDEN,
            __('site_settings.api_tokens.api.missing_ability', ['ability' => $ability->value]),
        );

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
