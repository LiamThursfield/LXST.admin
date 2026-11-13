<?php

namespace App\Http\Controllers\Tenant\Admin\Catalogue;

use App\Enums\Catalogue\TagKind;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Catalogue\TagIndexQuery;
use App\Http\Requests\Tenant\Admin\Catalogue\TagIndexRequest;
use App\Http\Requests\Tenant\Admin\Catalogue\TagRequest;
use App\Http\Resources\Tenant\Admin\Catalogue\TagResource;
use App\Models\Catalogue\Tag;
use App\Services\Catalogue\CatalogueTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(TagIndexRequest $request, TagIndexQuery $query, CatalogueTags $presets): Response
    {
        $canCreate = $request->user()?->can('create', Tag::class) ?? false;

        return Inertia::render('admin/catalogue/tag/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('catalogue.tags.columns.name')],
                ['accessorKey' => 'kind_label', 'header' => __('catalogue.tags.columns.kind')],
                ['accessorKey' => 'items_count', 'header' => __('catalogue.tags.columns.items')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'tags' => TagResource::collection($query->handle($request)),
            'presetGroups' => $canCreate ? $presets->groups() : [],
            'canCreate' => $canCreate,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Tag::class);

        return Inertia::render('admin/catalogue/tag/Form', $this->formOptions());
    }

    public function store(TagRequest $request): RedirectResponse
    {
        Tag::create($request->tagAttributes());

        return redirect()->route('admin.catalogue.tags.index')
            ->with('success', __('catalogue.tags.flash.created'));
    }

    public function edit(Tag $tag): Response
    {
        Gate::authorize('update', $tag);

        return Inertia::render('admin/catalogue/tag/Form', [
            'tag' => new TagResource($tag),
            ...$this->formOptions(),
        ]);
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->tagAttributes());

        return redirect()->route('admin.catalogue.tags.index')
            ->with('success', __('catalogue.tags.flash.updated'));
    }

    /**
     * Deletes the tag, taking it off every item
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $tag->delete();

        return redirect()->back()->with('success', __('catalogue.tags.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'kinds' => TagKind::asSelectItems(),
            'icons' => config('catalogue.tag_icons'),
        ];
    }
}
