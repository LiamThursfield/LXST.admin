<?php

namespace App\Http\Middleware;

use App\Enums\AppContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function rootView(Request $request): string
    {
        // If a tenant is identified, use the tenant blade, otherwise central
        return tenant() ? 'tenant' : 'central';
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = tenant() ? AppContext::Tenant : AppContext::Central;

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'context' => $context,
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
