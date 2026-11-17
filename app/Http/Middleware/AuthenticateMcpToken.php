<?php

namespace App\Http\Middleware;

use App\Enums\Api\ApiTokenAbility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the MCP server: only lets the request through with a tenant API token (as for
 * `auth.tenant-token`) that has at least one `mcp:*` ability. Each tool then checks the ability
 * it needs. Runs before the MCP message is read, so nothing reaches the MCP server without one.
 *
 * Usage: Route::middleware(['auth.tenant-token.mcp', 'throttle:tenant-api-token'])
 */
class AuthenticateMcpToken extends AuthenticateTenantToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability = ''): Response
    {
        $token = $this->authenticatedToken();

        $hasMcpAbility = collect(ApiTokenAbility::mcp())
            ->contains(fn (ApiTokenAbility $mcpAbility) => in_array($mcpAbility->value, $token->abilities ?? [], true));

        abort_unless($hasMcpAbility, Response::HTTP_FORBIDDEN, __('site_settings.api_tokens.api.missing_mcp_ability'));

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
