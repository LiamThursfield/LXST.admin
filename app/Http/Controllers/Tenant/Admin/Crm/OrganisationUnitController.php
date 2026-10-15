<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Enums\Crm\OrganisationUnitType;
use App\Enums\Crm\SocialMedia;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Crm\OrganisationUnitIndexQuery;
use App\Http\Requests\Tenant\Admin\Crm\OrganisationUnitIndexRequest;
use App\Http\Requests\Tenant\Admin\Crm\OrganisationUnitStoreRequest;
use App\Http\Requests\Tenant\Admin\Crm\OrganisationUnitUpdateRequest;
use App\Http\Resources\Tenant\Admin\Crm\OrganisationUnitResource;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationUnitController extends Controller
{
    public function index(OrganisationUnitIndexRequest $request, OrganisationUnitIndexQuery $query): Response
    {
        return Inertia::render('admin/crm/organisation-unit/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('crm.organisation_units.columns.name')],
                ['accessorKey' => 'type', 'header' => __('crm.organisation_units.columns.type')],
                ['accessorKey' => 'parent', 'header' => __('crm.organisation_units.columns.parent')],
                ['accessorKey' => 'email', 'header' => __('crm.organisation_units.columns.email')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'organisationUnits' => OrganisationUnitResource::collection($query->handle($request)),
            'types' => OrganisationUnitType::asSelectItems(),
            'canCreate' => $request->user()?->can('create', OrganisationUnit::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', OrganisationUnit::class);

        return Inertia::render('admin/crm/organisation-unit/Create', $this->formOptions());
    }

    public function store(OrganisationUnitStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $organisationUnit = OrganisationUnit::create($request->organisationUnitAttributes());

            $this->syncAddress($organisationUnit, $request->addressAttributes());
        });

        return redirect()->route('admin.crm.organisation-units.index')
            ->with('success', __('crm.organisation_units.flash.created'));
    }

    public function show(OrganisationUnit $organisationUnit): Response
    {
        Gate::authorize('view', $organisationUnit);

        return Inertia::render('admin/crm/organisation-unit/Show', [
            'organisationUnit' => new OrganisationUnitResource($organisationUnit->load('parent', 'address')),
            ...$this->formOptions(),
        ]);
    }

    public function edit(OrganisationUnit $organisationUnit): Response
    {
        Gate::authorize('update', $organisationUnit);

        return Inertia::render('admin/crm/organisation-unit/Edit', [
            'organisationUnit' => new OrganisationUnitResource($organisationUnit->load('parent', 'address')),
            ...$this->formOptions($organisationUnit),
        ]);
    }

    public function update(OrganisationUnitUpdateRequest $request, OrganisationUnit $organisationUnit): RedirectResponse
    {
        DB::transaction(function () use ($request, $organisationUnit) {
            $organisationUnit->update($request->organisationUnitAttributes());

            $this->syncAddress($organisationUnit, $request->addressAttributes());
        });

        return redirect()->route('admin.crm.organisation-units.index')
            ->with('success', __('crm.organisation_units.flash.updated'));
    }

    /**
     * @throws ValidationException
     */
    public function destroy(OrganisationUnit $organisationUnit): RedirectResponse
    {
        Gate::authorize('delete', $organisationUnit);

        if ($organisationUnit->children()->exists()) {
            throw ValidationException::withMessages([
                'organisation_unit' => __('crm.organisation_units.validation.has_children'),
            ]);
        }

        DB::transaction(function () use ($organisationUnit) {
            $organisationUnit->address()->delete();
            $organisationUnit->delete();
        });

        return redirect()->back()->with('success', __('crm.organisation_units.flash.deleted'));
    }

    /**
     * The select options the create/edit/show form needs
     *
     * @return array<string, mixed>
     */
    protected function formOptions(?OrganisationUnit $organisationUnit = null): array
    {
        $parents = OrganisationUnit::query()
            // A unit cannot be its own parent
            ->when($organisationUnit, fn ($query, OrganisationUnit $unit) => $query->whereKeyNot($unit->id))
            ->orderBy('name')
            ->get()
            ->map(fn (OrganisationUnit $unit) => [
                'value' => $unit->id,
                'label' => $unit->name,
                'type' => $unit->type->value,
            ])
            ->values();

        return [
            'types' => OrganisationUnitType::asSelectItems(),
            'allowedParentTypes' => collect(OrganisationUnitType::cases())->mapWithKeys(fn (OrganisationUnitType $type) => [
                $type->value => array_map(fn (OrganisationUnitType $parentType) => $parentType->value, $type->allowedParentTypes()),
            ]),
            'parentOptions' => $parents,
            'socialMedia' => SocialMedia::asSelectItems(),
        ];
    }

    /**
     * Creates, updates or removes the unit's address to match the submitted form
     *
     * @param  array<string, mixed>|null  $attributes
     */
    protected function syncAddress(OrganisationUnit $organisationUnit, ?array $attributes): void
    {
        if ($attributes === null) {
            $organisationUnit->address()->delete();

            return;
        }

        $organisationUnit->address()->updateOrCreate([], $attributes);
    }
}
