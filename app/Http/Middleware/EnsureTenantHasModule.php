<?php

namespace App\Http\Middleware;

use App\Enums\TenantModule;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only allows the request through when the current tenant has the given module switched on.
 * Responds with a 404 otherwise, as the module's pages don't exist for that tenant.
 *
 * Usage: Route::middleware('tenant.module:tracker')
 */
class EnsureTenantHasModule
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = tenant();

        abort_unless(
            $tenant instanceof Tenant && $tenant->hasModule(TenantModule::from($module)),
            Response::HTTP_NOT_FOUND
        );

        return $next($request);
    }
}
