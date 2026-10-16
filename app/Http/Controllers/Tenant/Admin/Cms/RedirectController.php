<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Enums\Cms\RedirectStatus;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Cms\RedirectIndexQuery;
use App\Http\Requests\Tenant\Admin\Cms\RedirectIndexRequest;
use App\Http\Requests\Tenant\Admin\Cms\RedirectStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\RedirectUpdateRequest;
use App\Http\Resources\Tenant\Admin\Cms\RedirectResource;
use App\Models\Cms\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RedirectController extends Controller
{
    public function index(RedirectIndexRequest $request, RedirectIndexQuery $query): Response
    {
        return Inertia::render('admin/cms/redirect/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'url_from', 'header' => __('cms.redirects.columns.from')],
                ['accessorKey' => 'url_to', 'header' => __('cms.redirects.columns.to')],
                ['accessorKey' => 'type', 'header' => __('cms.redirects.columns.type')],
                ['accessorKey' => 'status', 'header' => __('cms.redirects.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'redirects' => RedirectResource::collection($query->handle($request)),
            'statuses' => RedirectStatus::asSelectItems(),
            'canCreate' => $request->user()?->can('create', Redirect::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Redirect::class);

        return Inertia::render('admin/cms/redirect/Create');
    }

    public function store(RedirectStoreRequest $request): RedirectResponse
    {
        Redirect::create($request->redirectAttributes());

        return redirect()->route('admin.cms.redirects.index')
            ->with('success', __('cms.redirects.flash.created'));
    }

    public function edit(Redirect $redirect): Response
    {
        Gate::authorize('update', $redirect);

        return Inertia::render('admin/cms/redirect/Edit', [
            'redirect' => new RedirectResource($redirect),
        ]);
    }

    public function update(RedirectUpdateRequest $request, Redirect $redirect): RedirectResponse
    {
        $redirect->update($request->redirectAttributes());

        return redirect()->route('admin.cms.redirects.index')
            ->with('success', __('cms.redirects.flash.updated'));
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        Gate::authorize('delete', $redirect);

        $redirect->delete();

        return redirect()->back()->with('success', __('cms.redirects.flash.deleted'));
    }
}
