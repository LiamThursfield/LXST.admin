<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a disabled tenant's site, admin and API from answering (503), until it's enabled again
 * from the central admin. A tenant that's still being set up (or whose setup failed) has no
 * database yet, so doesn't answer either.
 */
class EnsureTenantIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        abort_if(
            $tenant instanceof Tenant && ! $tenant->isReady(),
            Response::HTTP_SERVICE_UNAVAILABLE,
            __('tenants.setting_up_message'),
        );

        abort_if(
            $tenant instanceof Tenant && $tenant->isDisabled(),
            Response::HTTP_SERVICE_UNAVAILABLE,
            __('tenants.disabled_message'),
        );

        return $next($request);
    }
}
