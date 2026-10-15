<?php

namespace App\Http\Controllers\Tenant\Admin\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Tracker\HolidayIndexQuery;
use App\Http\Requests\Tenant\Admin\Tracker\HolidayIndexRequest;
use App\Http\Requests\Tenant\Admin\Tracker\HolidayStoreRequest;
use App\Http\Requests\Tenant\Admin\Tracker\HolidayUpdateRequest;
use App\Http\Resources\Tenant\Admin\Tracker\HolidayResource;
use App\Models\Tracker\Holiday;
use App\Models\User;
use App\Services\Tracker\HolidayReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(protected HolidayReconciler $reconciler) {}

    public function index(HolidayIndexRequest $request, HolidayIndexQuery $query): Response
    {
        return Inertia::render('admin/tracker/holiday/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'label', 'header' => 'Label'],
                ['accessorKey' => 'starts_on', 'header' => 'Starts'],
                ['accessorKey' => 'ends_on', 'header' => 'Ends'],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'holidays' => HolidayResource::collection($query->handle($request)),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Holiday::class);

        return Inertia::render('admin/tracker/holiday/Create');
    }

    public function store(HolidayStoreRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Holiday::create([
            ...$request->only('label', 'starts_on', 'ends_on'),
            'user_id' => $user->id,
        ]);

        $this->reconciler->reconcileForUser($user);

        return redirect()->route('admin.tracker.holidays.index')
            ->with('success', 'Holiday created successfully.');
    }

    public function show(Holiday $holiday): Response
    {
        Gate::authorize('view', $holiday);

        return Inertia::render('admin/tracker/holiday/Show', [
            'holiday' => new HolidayResource($holiday),
        ]);
    }

    public function edit(Holiday $holiday): Response
    {
        Gate::authorize('update', $holiday);

        return Inertia::render('admin/tracker/holiday/Edit', [
            'holiday' => new HolidayResource($holiday),
        ]);
    }

    public function update(HolidayUpdateRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->only('label', 'starts_on', 'ends_on'));

        $this->reconciler->reconcileForUser($holiday->user);

        return redirect()->route('admin.tracker.holidays.index')
            ->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('delete', $holiday);

        $user = $holiday->user;
        $holiday->delete();

        $this->reconciler->reconcileForUser($user);

        return redirect()->back()->with('success', 'Holiday deleted successfully.');
    }
}
