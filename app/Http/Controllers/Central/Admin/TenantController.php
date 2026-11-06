<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Central\Admin\TenantIndexQuery;
use App\Http\Requests\Central\Admin\TenantDestroyRequest;
use App\Http\Requests\Central\Admin\TenantIndexRequest;
use App\Http\Requests\Central\Admin\TenantStoreRequest;
use App\Http\Requests\Central\Admin\TenantUpdateRequest;
use App\Http\Resources\Central\Admin\TenantResource;
use App\Models\Tenant;
use App\Services\Central\TenantProvisioner;
use App\Services\Cms\Api\ContentVersion;
use App\Services\Industry\IndustryProfile;
use App\Services\Industry\IndustryProfiles;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                ['accessorKey' => 'industry', 'header' => __('tenants.columns.industry')],
                ['accessorKey' => 'modules', 'header' => __('tenants.columns.modules')],
                ['accessorKey' => 'created_at', 'header' => __('tenants.columns.created_at')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'tenants' => TenantResource::collection($query->handle($request)),
            'modules' => TenantModule::asSelectItems(),
        ]);
    }

    public function create(IndustryProfiles $profiles): Response
    {
        return Inertia::render('admin/tenant/Create', [
            'modules' => TenantModule::asSelectItems(),
            'industries' => $this->industryOptions($profiles),
        ]);
    }

    /**
     * Creating the tenant's database runs straight away, so a failure (e.g. the database server
     * refusing) is shown on the form rather than leaving a half-made tenant behind
     */
    public function store(TenantStoreRequest $request, TenantProvisioner $provisioner): RedirectResponse
    {
        try {
            $tenant = $provisioner->provision(
                $request->string('name')->toString(),
                $request->string('domain')->toString(),
                $request->modules(),
                $request->admin(),
                $request->validated('industry'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('tenants.flash.create_failed', ['message' => $exception->getMessage()]));
        }

        return redirect()->route('central.admin.tenants.show', $tenant)
            ->with('success', $request->admin() === null
                ? __('tenants.flash.created', ['name' => $tenant->name])
                : __('tenants.flash.created_and_invited', ['name' => $tenant->name, 'email' => $request->admin()['email']]));
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load('domains');

        return Inertia::render('admin/tenant/Show', [
            'tenant' => new TenantResource($tenant),
        ]);
    }

    public function edit(Tenant $tenant, IndustryProfiles $profiles): Response
    {
        $tenant->load('domains');

        return Inertia::render('admin/tenant/Edit', [
            'tenant' => new TenantResource($tenant),
            'modules' => TenantModule::asSelectItems(),
            'industries' => $this->industryOptions($profiles),
        ]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'name' => $request->string('name')->toString(),
            'modules' => $request->modules(),
            'industry' => $request->validated('industry', $tenant->industry),
            'platform_fee_percent' => $request->filled('platform_fee_percent') ? $request->validated('platform_fee_percent') : null,
            // Entered in pounds, kept in pence
            'platform_fee_fixed' => $request->filled('platform_fee_fixed') ? (int) round((float) $request->validated('platform_fee_fixed') * 100) : null,
        ]);

        // The tenant's headless API shows its industry and words
        if ($tenant->wasChanged('industry')) {
            $tenant->run(fn () => app(ContentVersion::class)->bump());
        }

        return redirect()->route('central.admin.tenants.show', $tenant)
            ->with('success', __('tenants.flash.updated', ['name' => $tenant->name]));
    }

    /**
     * @return list<array{value: string, label: string, description: string, suggested_modules: list<string>}>
     */
    protected function industryOptions(IndustryProfiles $profiles): array
    {
        return array_map(fn (IndustryProfile $profile) => [
            ...$profile->toSelectItem(),
            'description' => $profile->description(),
            'suggested_modules' => array_map(fn (TenantModule $module) => $module->value, $profile->suggestedModules),
        ], $profiles->all());
    }

    /**
     * Switches the tenant off: its site, admin and API stop answering (EnsureTenantIsEnabled)
     */
    public function disable(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['disabled_at' => now()]);

        return back()->with('success', __('tenants.flash.disabled', ['name' => $tenant->name]));
    }

    public function enable(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['disabled_at' => null]);

        return back()->with('success', __('tenants.flash.enabled', ['name' => $tenant->name]));
    }

    /**
     * Deletes a disabled tenant, along with its domains and database (the tenancy pipeline)
     */
    public function destroy(TenantDestroyRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('central.admin.tenants.index')
            ->with('success', __('tenants.flash.deleted', ['name' => $tenant->name]));
    }
}
