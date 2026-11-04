<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\ResourceType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\ResourceIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\ResourceIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ResourceStoreRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ResourceUpdateRequest;
use App\Http\Resources\Tenant\Admin\Bookings\ResourceResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Crm\OrganisationUnit;
use App\Models\User;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(ResourceIndexRequest $request, ResourceIndexQuery $query): Response
    {
        $terminology = app(Terminology::class);

        return Inertia::render('admin/bookings/resource/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('bookings.resources.columns.name')],
                ['accessorKey' => 'type_label', 'header' => __('bookings.resources.columns.type')],
                ['accessorKey' => 'location_name', 'header' => $terminology->translate('bookings.resources.columns.location')],
                ['accessorKey' => 'services_count', 'header' => $terminology->translate('bookings.resources.columns.services')],
                ['accessorKey' => 'is_active', 'header' => __('bookings.resources.columns.active')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'resources' => ResourceResource::collection($query->handle($request)),
            'types' => ResourceType::asSelectItems(),
            'canCreate' => $request->user()?->can('create', BookableResource::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', BookableResource::class);

        return Inertia::render('admin/bookings/resource/Create', $this->formOptions());
    }

    public function store(ResourceStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $resource = BookableResource::create($request->resourceAttributes());

            $resource->workingHours()->createMany($request->workingHoursAttributes());
            $resource->services()->sync($request->servicesAttributes());
        });

        return redirect()->route('admin.bookings.resources.index')
            ->with('success', app(Terminology::class)->translate('bookings.resources.flash.created'));
    }

    public function show(BookableResource $resource): Response
    {
        Gate::authorize('view', $resource);

        return Inertia::render('admin/bookings/resource/Show', [
            'bookableResource' => new ResourceResource($resource->load(['workingHours', 'services'])),
            ...$this->formOptions($resource),
        ]);
    }

    public function edit(BookableResource $resource): Response
    {
        Gate::authorize('update', $resource);

        return Inertia::render('admin/bookings/resource/Edit', [
            'bookableResource' => new ResourceResource($resource->load(['workingHours', 'services'])),
            ...$this->formOptions($resource),
        ]);
    }

    public function update(ResourceUpdateRequest $request, BookableResource $resource): RedirectResponse
    {
        DB::transaction(function () use ($request, $resource) {
            $resource->update($request->resourceAttributes());

            $resource->workingHours()->delete();
            $resource->workingHours()->createMany($request->workingHoursAttributes());
            $resource->services()->sync($request->servicesAttributes());
        });

        return redirect()->route('admin.bookings.resources.index')
            ->with('success', app(Terminology::class)->translate('bookings.resources.flash.updated'));
    }

    /**
     * Soft deletes the resource, freeing up its user to be linked again
     */
    public function destroy(BookableResource $resource): RedirectResponse
    {
        Gate::authorize('delete', $resource);

        $resource->delete();

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.resources.flash.deleted'));
    }

    /**
     * The select options the resource form needs. Only users not yet linked to someone else are offered.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(?BookableResource $resource = null): array
    {
        $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;

        return [
            'types' => ResourceType::asSelectItems(),
            'hasLocations' => $hasLocations,
            'locations' => $hasLocations
                ? OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
                    ->map(fn (OrganisationUnit $location) => ['value' => $location->id, 'label' => $location->name])
                    ->values()
                : [],
            'users' => User::query()
                ->whereNotIn('id', BookableResource::query()->whereNotNull('user_id')->whereKeyNot($resource->id ?? 0)->select('user_id'))
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])
                ->values(),
            'services' => Service::query()->orderBy('name')->get()
                ->map(fn (Service $service) => ['value' => $service->id, 'label' => $service->name])
                ->values(),
            'currency' => Service::CURRENCY,
        ];
    }
}
