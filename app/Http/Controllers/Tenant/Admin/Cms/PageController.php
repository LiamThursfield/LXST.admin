<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\PageIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\PageIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\PageStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\PageUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\PageResource;
use App\Http\Resources\Tenant\Admin\Cms\TemplateResource;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\Url;
use App\Services\Cms\ContentFieldOptions;
use App\Services\Cms\PageUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        protected PageUrls $urls,
        protected ContentFieldOptions $fieldOptions,
    ) {}

    public function index(PageIndexRequest $request, PageIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/page/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('cms.pages.columns.name')],
                ['accessorKey' => 'url', 'header' => __('cms.pages.columns.url')],
                ['accessorKey' => 'template', 'header' => __('cms.pages.columns.template')],
                ['accessorKey' => 'status', 'header' => __('cms.pages.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'pages' => PageResource::collection($query->handle($request)),
            'statuses' => PublishStatus::asSelectItems(),
            'canCreate' => $request->user()?->can('create', Page::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('admin/cms/page/Create', [
            ...$this->editorOptions(),
            'defaultParentId' => $request->integer('parent_id') ?: null,
        ]);
    }

    public function store(PageStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $page = Page::create($request->pageAttributes());

            $this->urls->sync($page, $request->publishingAttributes());
            $page->metadata()->create($request->metadataAttributes());
            $page->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.pages.index')
            ->with('success', __('cms.pages.flash.created'));
    }

    public function show(Page $page): Response
    {
        Gate::authorize('view', $page);

        return Inertia::render('admin/cms/page/Show', [
            'page' => new PageResource($page->load('url', 'metadata', 'template', 'content')),
            ...$this->editorOptions($page),
        ]);
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('admin/cms/page/Edit', [
            'page' => new PageResource($page->load('url', 'metadata', 'template', 'content')),
            ...$this->editorOptions($page),
        ]);
    }

    public function update(PageUpdateRequest $request, Page $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $page) {
            $page->update($request->pageAttributes());

            // Rebuilds the children's URLs too, if the slug or parent changed
            $this->urls->sync($page, $request->publishingAttributes());
            $page->metadata()->updateOrCreate([], $request->metadataAttributes());
            $page->syncContent($request->contentValues());
        });

        return redirect()->route('admin.cms.pages.index')
            ->with('success', __('cms.pages.flash.updated'));
    }

    /**
     * Pages with children can't be deleted, unless `force` is sent, which deletes the whole
     * branch. The admin doesn't send it yet (#90 adds a confirmation for it).
     */
    public function destroy(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $descendants = $page->descendants();

        if ($descendants->isNotEmpty() && ! $request->boolean('force')) {
            throw ValidationException::withMessages([
                'page' => __('cms.pages.validation.has_children', ['count' => $descendants->count()]),
            ]);
        }

        DB::transaction(function () use ($page, $descendants) {
            // Deepest first, so no page is deleted while it still has children
            foreach ([...$descendants->reverse()->all(), $page] as $doomed) {
                $doomed->content()->delete();
                $doomed->metadata()->delete();
                $doomed->url()->delete();
                $doomed->delete();
            }
        });

        return redirect()->back()->with('success', trans_choice('cms.pages.flash.deleted', $descendants->count() + 1));
    }

    /**
     * The URL a page would have, and whether it's free, for the editor's live check
     */
    public function urlCheck(Request $request): JsonResponse
    {
        $page = $request->filled('page_id') ? Page::query()->find($request->integer('page_id')) : null;

        Gate::authorize($page ? 'update' : 'create', $page ?? Page::class);

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $slug = strtolower(trim((string) ($validated['slug'] ?? ''), '/ '));
        $parent = isset($validated['parent_id']) ? Page::query()->with('url')->whereKey((int) $validated['parent_id'])->first() : null;
        $url = (new Page(['slug' => $slug]))->urlFull($parent?->url?->url_full);

        $taken = Url::query()
            ->where('url_full', $url)
            ->when($page?->url, fn ($query, $own) => $query->whereKeyNot($own->id))
            ->exists();

        return response()->json(['url' => $url, 'available' => ! $taken]);
    }

    /**
     * The choices for the page editor: page templates (with fields), the component and repeater
     * templates nested content uses, layouts, and the pages that can be a parent
     *
     * @return array<string, mixed>
     */
    protected function editorOptions(?Page $page = null): array
    {
        $excluded = $page === null ? [] : [$page->id, ...$page->descendants()->pluck('id')->all()];

        return [
            'templates' => $this->templates([TemplateType::Page]),
            'nestedTemplates' => $this->templates([TemplateType::Component, TemplateType::Repeater]),
            // For menu and CRM fields (not `menus`, which is the shared sidebar navigation prop)
            'menuOptions' => $this->fieldOptions->menus(),
            'crmOptions' => $this->fieldOptions->crm(),
            'layouts' => Layout::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Layout $layout) => ['value' => $layout->id, 'label' => $layout->name]),
            // Not this page or anything under it (that would loop), and not the home page (its
            // children's URLs would clash with top-level pages)
            'parents' => Page::query()
                ->with('url')
                ->whereKeyNot($excluded)
                ->where('slug', '!=', '')
                ->get()
                ->sortBy(fn (Page $parent) => $parent->url->url_full ?? '')
                ->map(fn (Page $parent) => [
                    'value' => $parent->id,
                    'label' => $parent->name.' ('.($parent->url->url_full ?? '?').')',
                ])
                ->values(),
        ];
    }

    /**
     * @param  list<TemplateType>  $types
     */
    protected function templates(array $types): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            Template::query()->whereIn('type', $types)->with(['fields', 'sections'])->orderBy('name')->get()
        );
    }
}
