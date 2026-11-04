<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\ServiceIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceStoreRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceUpdateRequest;
use App\Http\Resources\Tenant\Admin\Bookings\ServiceResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Crm\OrganisationUnit;
use App\Models\Tenant;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(ServiceIndexRequest $request, ServiceIndexQuery $query): Response
    {
        return Inertia::render('admin/bookings/service/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('bookings.services.columns.name')],
                ['accessorKey' => 'category_name', 'header' => __('bookings.services.columns.category')],
                ['accessorKey' => 'booking_type_label', 'header' => __('bookings.services.columns.booking_type')],
                ['accessorKey' => 'price', 'header' => __('bookings.services.columns.price')],
                ['accessorKey' => 'is_bookable_online', 'header' => __('bookings.services.columns.online')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'services' => ServiceResource::collection($query->handle($request)),
            'currency' => Service::CURRENCY,
            'canCreate' => $request->user()?->can('create', Service::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Service::class);

        return Inertia::render('admin/bookings/service/Create', $this->formOptions());
    }

    public function store(ServiceStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $service = Service::create($request->serviceAttributes());

            $this->saveRelations($service, $request->optionsAttributes(), $request->locationIds());
        });

        return redirect()->route('admin.bookings.services.index')
            ->with('success', app(Terminology::class)->translate('bookings.services.flash.created'));
    }

    public function show(Request $request, Service $service): Response
    {
        Gate::authorize('view', $service);

        return Inertia::render('admin/bookings/service/Show', [
            'service' => new ServiceResource($service->load($this->relationsFor($request))),
            ...$this->formOptions(),
        ]);
    }

    public function edit(Request $request, Service $service): Response
    {
        Gate::authorize('update', $service);

        return Inertia::render('admin/bookings/service/Edit', [
            'service' => new ServiceResource($service->load($this->relationsFor($request))),
            ...$this->formOptions(),
        ]);
    }

    public function update(ServiceUpdateRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            $service->update($request->serviceAttributes());

            $this->saveRelations($service, $request->optionsAttributes(), $request->locationIds());
        });

        return redirect()->route('admin.bookings.services.index')
            ->with('success', app(Terminology::class)->translate('bookings.services.flash.updated'));
    }

    /**
     * Soft deletes the service; its options and locations are kept with it
     */
    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.services.flash.deleted'));
    }

    /**
     * What a service's form shows, with who can do it only for those who can see resources
     *
     * @return list<string>
     */
    protected function relationsFor(Request $request): array
    {
        return [
            'options',
            'locations',
            ...($request->user()?->can('viewAny', BookableResource::class) ? ['resources'] : []),
        ];
    }

    /**
     * Makes the service's options match the submitted list, keeping the ids of those that stay, and
     * chooses its locations unless they aren't available to change
     *
     * @param  list<array{id: int|null, type: string, name: string, price_adjustment: int, duration_adjustment: int, order: int}>  $options
     * @param  list<int>|null  $locationIds
     */
    protected function saveRelations(Service $service, array $options, ?array $locationIds): void
    {
        $service->options()->whereNotIn('id', array_filter(array_column($options, 'id')))->delete();

        foreach ($options as $option) {
            $service->options()->updateOrCreate(['id' => $option['id']], Arr::except($option, 'id'));
        }

        if ($locationIds !== null) {
            $service->locations()->sync($locationIds);
        }
    }

    /**
     * The select options the service form needs. The tenant's own booking types come first, and the
     * first is what a new service starts as.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $tenant = tenant();
        $tenantTypes = $tenant instanceof Tenant ? $tenant->bookingTypes() : [];
        $hasLocations = $tenant instanceof Tenant && $tenant->hasModule(TenantModule::Crm);

        return [
            'bookingTypes' => collect([...$tenantTypes, ...BookingType::cases()])
                ->unique(fn (BookingType $type) => $type->value)
                ->map(fn (BookingType $type) => [...$type->toSelectItem(), 'details' => $type->details()])
                ->values(),
            'priceTypes' => ServicePriceType::asSelectItems(),
            'hireUnits' => HireUnit::asSelectItems(),
            'optionTypes' => ServiceOptionType::asSelectItems(),
            'categories' => ServiceCategory::query()->orderBy('order')->orderBy('name')->get()
                ->map(fn (ServiceCategory $category) => ['value' => $category->id, 'label' => $category->name])
                ->values(),
            'hasLocations' => $hasLocations,
            'locations' => $hasLocations
                ? OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
                    ->map(fn (OrganisationUnit $location) => ['value' => $location->id, 'label' => $location->name])
                    ->values()
                : [],
            'currency' => Service::CURRENCY,
        ];
    }
}
