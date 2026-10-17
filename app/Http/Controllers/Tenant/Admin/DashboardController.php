<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\Cms\AnalyticsRange;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\PageViewAnalyticsQuery;
use App\Http\Requests\Tenant\Admin\DashboardRequest;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\CmsPermission;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, PageViewAnalyticsQuery $analytics): Response
    {
        $tenant = tenant();
        $canViewAnalytics = $tenant instanceof Tenant
            && $tenant->hasModule(TenantModule::Cms)
            && ($request->user()?->can(CmsPermission::ViewAnalytics) ?? false);
        $range = $request->analyticsRange();

        return Inertia::render('admin/Dashboard', [
            // Loaded after the page, as it counts every view in the range
            'analytics' => $canViewAnalytics
                ? Inertia::defer(fn () => $analytics->handle($range))
                : null,
            'analyticsRanges' => $canViewAnalytics ? AnalyticsRange::asSelectItems() : [],
        ]);
    }
}
