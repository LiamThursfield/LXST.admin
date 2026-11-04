<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Bookings\TimeOffType;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\TimeOffIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\TimeOffIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\TimeOffStoreRequest;
use App\Http\Resources\Tenant\Admin\Bookings\TimeOffResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\TimeOff;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffController extends Controller
{
    public function index(TimeOffIndexRequest $request, TimeOffIndexQuery $query): Response
    {
        return Inertia::render('admin/bookings/time-off/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'resource_name', 'header' => app(Terminology::class)->translate('bookings.time_off.columns.resource')],
                ['accessorKey' => 'type_label', 'header' => __('bookings.time_off.columns.type')],
                ['accessorKey' => 'starts_on', 'header' => __('bookings.time_off.columns.starts_on')],
                ['accessorKey' => 'ends_on', 'header' => __('bookings.time_off.columns.ends_on')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.time_off.columns.status')],
                ['accessorKey' => 'reason', 'header' => __('bookings.time_off.columns.reason')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'timeOff' => TimeOffResource::collection($query->handle($request)),
            'statuses' => TimeOffStatus::asSelectItems(),
            'canCreate' => $this->resourcesFor($request) !== [],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', TimeOff::class);

        return Inertia::render('admin/bookings/time-off/Create', [
            'resources' => $this->resourcesFor($request),
            'types' => TimeOffType::asSelectItems(),
            'canManage' => $request->user()?->can('manage', TimeOff::class) ?? false,
        ]);
    }

    /**
     * Time off recorded by someone who manages it needs no approval; anyone else's is a request
     */
    public function store(TimeOffStoreRequest $request): RedirectResponse
    {
        $canManage = $request->canManage();
        $validated = $request->validated();

        TimeOff::query()->create([
            ...$validated,
            'ends_on' => $validated['ends_on'] ?? $validated['starts_on'],
            'status' => $canManage ? TimeOffStatus::Approved : TimeOffStatus::Requested,
            'requested_by_id' => $request->user()?->id,
            'reviewed_by_id' => $canManage ? $request->user()?->id : null,
            'reviewed_at' => $canManage ? now() : null,
        ]);

        return redirect()->route('admin.bookings.time-off.index')
            ->with('success', __($canManage ? 'bookings.time_off.flash.recorded' : 'bookings.time_off.flash.requested'));
    }

    public function approve(Request $request, TimeOff $timeOff): RedirectResponse
    {
        return $this->review($request, $timeOff, TimeOffStatus::Approved, 'bookings.time_off.flash.approved');
    }

    public function decline(Request $request, TimeOff $timeOff): RedirectResponse
    {
        return $this->review($request, $timeOff, TimeOffStatus::Declined, 'bookings.time_off.flash.declined');
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        Gate::authorize('delete', $timeOff);

        $timeOff->delete();

        return redirect()->back()->with('success', __('bookings.time_off.flash.deleted'));
    }

    protected function review(Request $request, TimeOff $timeOff, TimeOffStatus $status, string $message): RedirectResponse
    {
        Gate::authorize('review', $timeOff);

        $timeOff->update([
            'status' => $status,
            'reviewed_by_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', __($message));
    }

    /**
     * Who the user can add time off for: anyone if they manage it, otherwise only themselves
     *
     * @return list<array{value: int, label: string}>
     */
    protected function resourcesFor(Request $request): array
    {
        $user = $request->user();

        if (! $user?->can('create', TimeOff::class)) {
            return [];
        }

        return array_values(BookableResource::query()
            ->when(! $user->can('manage', TimeOff::class), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('name')
            ->get()
            ->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])
            ->all());
    }
}
