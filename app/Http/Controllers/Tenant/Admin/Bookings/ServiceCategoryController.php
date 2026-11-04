<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\ServiceCategoryIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceCategoryIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceCategoryStoreRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceCategoryUpdateRequest;
use App\Http\Resources\Tenant\Admin\Bookings\ServiceCategoryResource;
use App\Models\Bookings\ServiceCategory;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function index(ServiceCategoryIndexRequest $request, ServiceCategoryIndexQuery $query): Response
    {
        return Inertia::render('admin/bookings/service-category/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('bookings.categories.columns.name')],
                ['accessorKey' => 'services_count', 'header' => app(Terminology::class)->translate('bookings.categories.columns.services')],
                ['accessorKey' => 'order', 'header' => __('bookings.categories.columns.order')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'categories' => ServiceCategoryResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', ServiceCategory::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ServiceCategory::class);

        return Inertia::render('admin/bookings/service-category/Create');
    }

    public function store(ServiceCategoryStoreRequest $request): RedirectResponse
    {
        ServiceCategory::create([...$request->validated(), 'order' => (int) $request->validated('order')]);

        return redirect()->route('admin.bookings.service-categories.index')
            ->with('success', __('bookings.categories.flash.created'));
    }

    public function edit(ServiceCategory $serviceCategory): Response
    {
        Gate::authorize('update', $serviceCategory);

        return Inertia::render('admin/bookings/service-category/Edit', [
            'category' => new ServiceCategoryResource($serviceCategory),
        ]);
    }

    public function update(ServiceCategoryUpdateRequest $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update([...$request->validated(), 'order' => (int) $request->validated('order')]);

        return redirect()->route('admin.bookings.service-categories.index')
            ->with('success', __('bookings.categories.flash.updated'));
    }

    /**
     * Deletes the category; its services stay, without a category
     */
    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        Gate::authorize('delete', $serviceCategory);

        $serviceCategory->delete();

        return redirect()->back()->with('success', __('bookings.categories.flash.deleted'));
    }
}
