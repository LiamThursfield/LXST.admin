<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\MenuIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\MenuIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\MenuStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\MenuUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\MenuResource;
use App\Models\Cms\Content;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(MenuIndexRequest $request, MenuIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/menu/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('cms.menus.columns.name')],
                ['accessorKey' => 'slug', 'header' => __('cms.menus.columns.slug')],
                ['accessorKey' => 'items_count', 'header' => __('cms.menus.columns.items')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            // Not `menus`, which is the shared sidebar navigation prop
            'cmsMenus' => MenuResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', Menu::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Menu::class);

        return Inertia::render('admin/cms/menu/Create', [
            'pages' => $this->pageOptions(),
        ]);
    }

    public function store(MenuStoreRequest $request): RedirectResponse
    {
        Menu::create($request->menuAttributes());

        return redirect()->route('admin.cms.menus.index')
            ->with('success', __('cms.menus.flash.created'));
    }

    public function show(Menu $menu): Response
    {
        Gate::authorize('view', $menu);

        return Inertia::render('admin/cms/menu/Show', [
            'menu' => new MenuResource($menu),
            'pages' => $this->pageOptions(),
        ]);
    }

    public function edit(Menu $menu): Response
    {
        Gate::authorize('update', $menu);

        return Inertia::render('admin/cms/menu/Edit', [
            'menu' => new MenuResource($menu),
            'pages' => $this->pageOptions(),
        ]);
    }

    public function update(MenuUpdateRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->menuAttributes());

        return redirect()->route('admin.cms.menus.index')
            ->with('success', __('cms.menus.flash.updated'));
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        Gate::authorize('delete', $menu);

        // Menu fields can only be on pages and layouts (not inside components or repeaters), so
        // their values are always a menu id at the top level of the content
        $inUse = Content::query()
            ->where('template_field_type', TemplateFieldType::CmsMenu)
            ->pluck('data')
            ->contains(fn (mixed $menuId) => (int) $menuId === $menu->id);

        if ($inUse) {
            throw ValidationException::withMessages([
                'menu' => __('cms.menus.validation.in_use'),
            ]);
        }

        $menu->delete();

        return redirect()->back()->with('success', __('cms.menus.flash.deleted'));
    }

    /**
     * The pages items can link to, in URL order
     *
     * @return array<int, array{value: int, label: string, url: string|null}>
     */
    protected function pageOptions(): array
    {
        return Page::query()
            ->with('url')
            ->get()
            ->sortBy(fn (Page $page) => $page->url->url_full ?? '')
            ->map(fn (Page $page) => [
                'value' => $page->id,
                'label' => $page->name.' ('.($page->url->url_full ?? '?').')',
                'url' => $page->url?->url_full,
            ])
            ->values()
            ->all();
    }
}
