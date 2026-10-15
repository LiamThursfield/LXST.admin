<?php

namespace App\Http\Controllers\Tenant\Admin\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Tracker\CategoryIndexQuery;
use App\Http\Requests\Tenant\Admin\Tracker\CategoryIndexRequest;
use App\Http\Requests\Tenant\Admin\Tracker\CategoryStoreRequest;
use App\Http\Requests\Tenant\Admin\Tracker\CategoryUpdateRequest;
use App\Http\Resources\Tenant\Admin\Tracker\CategoryResource;
use App\Models\Tracker\Category;
use App\Services\Authorisation\Enums\TrackerPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(CategoryIndexRequest $request, CategoryIndexQuery $query): Response
    {
        return Inertia::render('admin/tracker/category/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => 'Name'],
                ['accessorKey' => 'is_public', 'header' => 'Visibility'],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'categories' => CategoryResource::collection($query->handle($request)),
            'canManagePublic' => $request->user()?->can(TrackerPermission::ManagePublicCategories) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('admin/tracker/category/Create', [
            'canManagePublic' => request()->user()?->can(TrackerPermission::ManagePublicCategories) ?? false,
        ]);
    }

    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        Category::create([
            ...$request->only('name', 'colour', 'icon', 'is_public'),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('admin.tracker.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category): Response
    {
        Gate::authorize('view', $category);

        return Inertia::render('admin/tracker/category/Show', [
            'category' => new CategoryResource($category),
            'canManagePublic' => request()->user()?->can(TrackerPermission::ManagePublicCategories) ?? false,
        ]);
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('admin/tracker/category/Edit', [
            'category' => new CategoryResource($category),
            'canManagePublic' => request()->user()?->can(TrackerPermission::ManagePublicCategories) ?? false,
        ]);
    }

    public function update(CategoryUpdateRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->only('name', 'colour', 'icon', 'is_public'));

        return redirect()->route('admin.tracker.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
