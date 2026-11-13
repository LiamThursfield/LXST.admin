<?php

namespace App\Http\Controllers\Tenant\Admin\Catalogue;

use App\Enums\Bookings\ServicePriceType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Catalogue\CatalogueIndexQuery;
use App\Http\Requests\Tenant\Admin\Catalogue\CatalogueIndexRequest;
use App\Http\Requests\Tenant\Admin\Catalogue\CatalogueRequest;
use App\Http\Resources\Tenant\Admin\Catalogue\CatalogueResource;
use App\Models\Bookings\Service;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Tag;
use App\Services\Catalogue\CatalogueContents;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    /** What the editor and the catalogue's page show of each item */
    protected const array CONTENTS = [
        'sections.items.variations',
        'sections.items.tags',
        'sections.items.service',
        'sections.items.image',
    ];

    public function __construct(protected CatalogueContents $contents, protected Terminology $terminology) {}

    public function index(CatalogueIndexRequest $request, CatalogueIndexQuery $query): Response
    {
        return Inertia::render('admin/catalogue/catalogue/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('catalogue.catalogues.columns.name')],
                ['accessorKey' => 'contents', 'header' => __('catalogue.catalogues.columns.contents')],
                ['accessorKey' => 'schedule', 'header' => __('catalogue.catalogues.columns.schedule')],
                ['accessorKey' => 'is_published', 'header' => __('catalogue.catalogues.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'catalogues' => CatalogueResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', Catalogue::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Catalogue::class);

        return Inertia::render('admin/catalogue/catalogue/Form', $this->formOptions($request));
    }

    public function store(CatalogueRequest $request): RedirectResponse
    {
        $catalogue = DB::transaction(function () use ($request) {
            $catalogue = Catalogue::create($request->catalogueAttributes());
            $this->contents->sync($catalogue, $request->contents());

            return $catalogue;
        });

        return redirect()->route('admin.catalogue.catalogues.show', $catalogue)
            ->with('success', $this->terminology->translate('catalogue.catalogues.flash.created'));
    }

    public function show(Request $request, Catalogue $catalogue): Response
    {
        Gate::authorize('view', $catalogue);

        return Inertia::render('admin/catalogue/catalogue/Show', [
            'catalogue' => new CatalogueResource($catalogue->load(self::CONTENTS)),
            'tags' => $this->tagItems(),
            'currency' => Catalogue::CURRENCY,
            'canEdit' => $request->user()?->can('update', $catalogue) ?? false,
            'canUpdateStock' => $request->user()?->can('updateStock', $catalogue) ?? false,
        ]);
    }

    public function edit(Request $request, Catalogue $catalogue): Response
    {
        Gate::authorize('update', $catalogue);

        return Inertia::render('admin/catalogue/catalogue/Form', [
            'catalogue' => new CatalogueResource($catalogue->load(self::CONTENTS)),
            ...$this->formOptions($request),
        ]);
    }

    public function update(CatalogueRequest $request, Catalogue $catalogue): RedirectResponse
    {
        DB::transaction(function () use ($request, $catalogue) {
            $catalogue->update($request->catalogueAttributes());
            $this->contents->sync($catalogue, $request->contents());
        });

        return redirect()->route('admin.catalogue.catalogues.show', $catalogue)
            ->with('success', $this->terminology->translate('catalogue.catalogues.flash.updated'));
    }

    /**
     * Deletes the catalogue with its sections, items and variations
     */
    public function destroy(Catalogue $catalogue): RedirectResponse
    {
        Gate::authorize('delete', $catalogue);

        $catalogue->delete();

        return redirect()->route('admin.catalogue.catalogues.index')
            ->with('success', $this->terminology->translate('catalogue.catalogues.flash.deleted'));
    }

    /**
     * What the editor needs to choose from. Services only with the Bookings module, for those who can
     * see them.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request): array
    {
        $canSeeServices = (tenant()?->hasModule(TenantModule::Bookings) ?? false)
            && ($request->user()?->can('viewAny', Service::class) ?? false);

        return [
            'tags' => $this->tagItems(),
            'services' => $canSeeServices
                ? Service::query()->orderBy('name')->get()->map(fn (Service $service) => [
                    'value' => $service->id,
                    'label' => $service->name,
                    'price' => $service->price_type->hasAmount() ? $service->price : null,
                    'from' => $service->price_type === ServicePriceType::From,
                ])->values()
                : null,
            'days' => collect(range(1, 7))->map(fn (int $day) => [
                'value' => $day,
                'label' => __("catalogue.schedule.days.{$day}"),
            ])->values(),
            'currency' => Catalogue::CURRENCY,
        ];
    }

    /**
     * Every tag, for choosing them and showing them on items
     *
     * @return list<array{value: int, label: string, icon: string|null, kind: string}>
     */
    protected function tagItems(): array
    {
        $tags = Tag::query()->orderBy('kind')->orderBy('order')->orderBy('name')->get();

        return array_values($tags->map(fn (Tag $tag) => [
            'value' => $tag->id,
            'label' => $tag->name,
            'icon' => $tag->icon,
            'kind' => $tag->kind->value,
        ])->all());
    }
}
