<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\TemplateType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\SavedComponentIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\SavedComponentIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\SavedComponentStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\SavedComponentUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\SavedComponentResource;
use App\Http\Resources\Tenant\Admin\Cms\TemplateResource;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SavedComponentController extends Controller
{
    public function index(SavedComponentIndexRequest $request, SavedComponentIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/saved-component/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('cms.saved_components.columns.name')],
                ['accessorKey' => 'slug', 'header' => __('cms.saved_components.columns.slug')],
                ['accessorKey' => 'template', 'header' => __('cms.saved_components.columns.template')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'savedComponents' => SavedComponentResource::collection($query->handle($request)),
            'templates' => $this->componentTemplates(withFields: false),
            'canCreate' => $request->user()?->can('create', SavedComponent::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SavedComponent::class);

        return Inertia::render('admin/cms/saved-component/Create', [
            'templates' => $this->componentTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function store(SavedComponentStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $savedComponent = SavedComponent::create($request->savedComponentAttributes());

            $savedComponent->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.saved-components.index')
            ->with('success', __('cms.saved_components.flash.created'));
    }

    public function show(SavedComponent $savedComponent): Response
    {
        Gate::authorize('view', $savedComponent);

        return Inertia::render('admin/cms/saved-component/Show', [
            'savedComponent' => new SavedComponentResource($savedComponent->load('template', 'content')),
            'templates' => $this->componentTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function edit(SavedComponent $savedComponent): Response
    {
        Gate::authorize('update', $savedComponent);

        return Inertia::render('admin/cms/saved-component/Edit', [
            'savedComponent' => new SavedComponentResource($savedComponent->load('template', 'content')),
            'templates' => $this->componentTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function update(SavedComponentUpdateRequest $request, SavedComponent $savedComponent): RedirectResponse
    {
        DB::transaction(function () use ($request, $savedComponent) {
            $savedComponent->update($request->savedComponentAttributes());

            // Content for fields that aren't in the (possibly changed) template is removed
            $savedComponent->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.saved-components.index')
            ->with('success', __('cms.saved_components.flash.updated'));
    }

    public function destroy(SavedComponent $savedComponent): RedirectResponse
    {
        Gate::authorize('delete', $savedComponent);

        DB::transaction(function () use ($savedComponent) {
            $savedComponent->content()->delete();
            $savedComponent->delete();
        });

        return redirect()->back()->with('success', __('cms.saved_components.flash.deleted'));
    }

    /**
     * The component templates to choose from, with their fields so the content editor can switch
     * between them
     */
    protected function componentTemplates(bool $withFields = true): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            Template::query()
                ->where('type', TemplateType::Component)
                ->when($withFields, fn ($query) => $query->with(['fields', 'sections']))
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * The component and repeater templates, with their fields, for editing nested content
     */
    protected function nestedTemplates(): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            Template::query()
                ->whereIn('type', [TemplateType::Component, TemplateType::Repeater])
                ->with(['fields', 'sections'])
                ->orderBy('name')
                ->get()
        );
    }
}
