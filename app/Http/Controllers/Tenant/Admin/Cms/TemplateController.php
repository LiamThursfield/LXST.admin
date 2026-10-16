<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\Crm\OrganisationUnitType;
use App\Http\Controllers\Concerns\SyncsBuilderFields;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\TemplateIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\TemplateIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\TemplateStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\TemplateUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\TemplateResource;
use App\Models\Cms\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    use SyncsBuilderFields;

    public function index(TemplateIndexRequest $request, TemplateIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/template/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('cms.templates.columns.name')],
                ['accessorKey' => 'type', 'header' => __('cms.templates.columns.type')],
                ['accessorKey' => 'slug', 'header' => __('cms.templates.columns.slug')],
                ['accessorKey' => 'fields_count', 'header' => __('cms.templates.columns.fields')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'templates' => TemplateResource::collection($query->handle($request)),
            'types' => TemplateType::asSelectItems(),
            'canCreate' => $request->user()?->can('create', Template::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Template::class);

        return Inertia::render('admin/cms/template/Create', $this->builderOptions());
    }

    public function store(TemplateStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $template = Template::create($request->templateAttributes());

            $this->syncBuilderFields($template->fields(), $request->fieldsAttributes());
        });

        return redirect()->route('admin.cms.templates.index')
            ->with('success', __('cms.templates.flash.created'));
    }

    public function show(Template $template): Response
    {
        Gate::authorize('view', $template);

        return Inertia::render('admin/cms/template/Show', [
            'template' => new TemplateResource($template->load('fields')),
            ...$this->builderOptions(),
        ]);
    }

    public function edit(Template $template): Response
    {
        Gate::authorize('update', $template);

        return Inertia::render('admin/cms/template/Edit', [
            'template' => new TemplateResource($template->load('fields')),
            'isInUse' => $template->isInUse(),
            ...$this->builderOptions(),
        ]);
    }

    public function update(TemplateUpdateRequest $request, Template $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template) {
            $template->update($request->templateAttributes());

            $this->syncBuilderFields($template->fields(), $request->fieldsAttributes());
        });

        return redirect()->route('admin.cms.templates.index')
            ->with('success', __('cms.templates.flash.updated'));
    }

    public function destroy(Template $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        if ($template->isInUse()) {
            throw ValidationException::withMessages([
                'template' => __('cms.templates.validation.in_use'),
            ]);
        }

        $template->delete();

        return redirect()->back()->with('success', __('cms.templates.flash.deleted'));
    }

    /**
     * The template types and field types for the builder. Each field type says which template
     * types allow it and whether it can be added (its editor exists, and any module it needs is on).
     *
     * @return array<string, mixed>
     */
    protected function builderOptions(): array
    {
        $tenant = tenant();

        return [
            'types' => TemplateType::asSelectItems(),
            'organisationUnitTypes' => OrganisationUnitType::asSelectItems(),
            // The templates component and repeater fields can use
            'nestedTemplates' => Template::query()
                ->whereIn('type', [TemplateType::Component, TemplateType::Repeater])
                ->orderBy('name')
                ->get(['id', 'type', 'name'])
                ->map(fn (Template $template) => ['id' => $template->id, 'type' => $template->type->value, 'name' => $template->name]),
            'fieldTypes' => collect(TemplateFieldType::cases())->map(fn (TemplateFieldType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'settingKeys' => $type->settingKeys(),
                'templateTypes' => collect(TemplateType::cases())
                    ->filter(fn (TemplateType $templateType) => $templateType->allowsFieldType($type))
                    ->map->value
                    ->values(),
                'isAvailable' => $type->isAvailable(),
                // Types needing a module the tenant doesn't have (e.g. CRM) aren't offered at all
                'needsModule' => $type->requiredModule() !== null && ! ($tenant !== null && $tenant->hasModule($type->requiredModule())),
            ])->values(),
        ];
    }
}
