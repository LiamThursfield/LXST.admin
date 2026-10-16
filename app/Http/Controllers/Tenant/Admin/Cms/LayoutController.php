<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\TemplateType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\LayoutIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\LayoutIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\LayoutStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\LayoutUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\LayoutResource;
use App\Http\Resources\Tenant\Admin\Cms\TemplateResource;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LayoutController extends Controller
{
    public function index(LayoutIndexRequest $request, LayoutIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/layout/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('cms.layouts.columns.name')],
                ['accessorKey' => 'slug', 'header' => __('cms.layouts.columns.slug')],
                ['accessorKey' => 'template', 'header' => __('cms.layouts.columns.template')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'layouts' => LayoutResource::collection($query->handle($request)),
            'templates' => $this->layoutTemplates(withFields: false),
            'canCreate' => $request->user()?->can('create', Layout::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Layout::class);

        return Inertia::render('admin/cms/layout/Create', [
            'templates' => $this->layoutTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function store(LayoutStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $layout = Layout::create($request->layoutAttributes());

            $layout->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.layouts.index')
            ->with('success', __('cms.layouts.flash.created'));
    }

    public function show(Layout $layout): Response
    {
        Gate::authorize('view', $layout);

        return Inertia::render('admin/cms/layout/Show', [
            'layout' => new LayoutResource($layout->load('template', 'content')),
            'templates' => $this->layoutTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function edit(Layout $layout): Response
    {
        Gate::authorize('update', $layout);

        return Inertia::render('admin/cms/layout/Edit', [
            'layout' => new LayoutResource($layout->load('template', 'content')),
            'templates' => $this->layoutTemplates(),
            'nestedTemplates' => $this->nestedTemplates(),
        ]);
    }

    public function update(LayoutUpdateRequest $request, Layout $layout): RedirectResponse
    {
        DB::transaction(function () use ($request, $layout) {
            $layout->update($request->layoutAttributes());

            // Content for fields that aren't in the (possibly changed) template is removed
            $layout->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.layouts.index')
            ->with('success', __('cms.layouts.flash.updated'));
    }

    public function destroy(Layout $layout): RedirectResponse
    {
        Gate::authorize('delete', $layout);

        if ($layout->pages()->exists()) {
            throw ValidationException::withMessages([
                'layout' => __('cms.layouts.validation.in_use'),
            ]);
        }

        DB::transaction(function () use ($layout) {
            $layout->content()->delete();
            $layout->delete();
        });

        return redirect()->back()->with('success', __('cms.layouts.flash.deleted'));
    }

    /**
     * The layout templates to choose from, with their fields so the content editor can switch
     * between them
     */
    protected function layoutTemplates(bool $withFields = true): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            Template::query()
                ->where('type', TemplateType::Layout)
                ->when($withFields, fn ($query) => $query->with('fields'))
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
                ->with('fields')
                ->orderBy('name')
                ->get()
        );
    }
}
