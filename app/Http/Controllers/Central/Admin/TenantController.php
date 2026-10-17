<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Central\Admin\TenantIndexQuery;
use App\Http\Requests\Central\Admin\TenantIndexRequest;
use App\Http\Resources\Central\Admin\TenantResource;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenants (sites) this app hosts. Every central user is a super user, so being logged in to
 * the central admin is enough.
 */
class TenantController extends Controller
{
    public function index(TenantIndexRequest $request, TenantIndexQuery $query): Response
    {
        return Inertia::render('admin/tenant/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'sort' => $request->validated('sort'),
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('tenants.columns.name')],
                ['accessorKey' => 'id', 'header' => __('tenants.columns.id')],
                ['accessorKey' => 'domains', 'header' => __('tenants.columns.domains')],
                ['accessorKey' => 'modules', 'header' => __('tenants.columns.modules')],
                ['accessorKey' => 'created_at', 'header' => __('tenants.columns.created_at')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'tenants' => TenantResource::collection($query->handle($request)),
            'modules' => TenantModule::asSelectItems(),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load('domains');

        return Inertia::render('admin/tenant/Show', [
            'tenant' => new TenantResource($tenant),
        ]);
    }
}
