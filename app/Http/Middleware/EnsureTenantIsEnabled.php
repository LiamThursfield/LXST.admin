<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a disabled tenant's site, admin and API from answering (503), until it's enabled again
 * from the central admin
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
            $tenant instanceof Tenant && $tenant->isDisabled(),
            Response::HTTP_SERVICE_UNAVAILABLE,
            __('tenants.disabled_message'),
        );

        return $next($request);
    }
}
