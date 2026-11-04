<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\RotaIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\RotaUpdateRequest;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Shift;
use App\Services\Bookings\ResourceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RotaController extends Controller
{
    /**
     * A week of everyone and everything active. Time off only shows as off, not why, as this page
     * can be seen by those who can't see the reason.
     */
    public function index(RotaIndexRequest $request, ResourceSchedule $schedule): Response
    {
        /** @var string|null $week */
        $week = $request->validated('week');
        $start = ($week === null ? CarbonImmutable::now() : CarbonImmutable::parse($week))->startOfWeek();
        $end = $start->addDays(6);

        $days = array_map(fn (int $offset) => $start->addDays($offset), range(0, 6));

        $resources = BookableResource::query()
            ->where('is_active', true)
            ->with([
                'workingHours',
                'shifts' => fn (HasMany $query) => $query->whereBetween('works_on', [$start->toDateString(), $end->toDateString()]),
                'timeOff' => fn (HasMany $query) => $query
                    ->where('status', TimeOffStatus::Approved)
                    ->whereDate('starts_on', '<=', $end->toDateString())
                    ->whereDate('ends_on', '>=', $start->toDateString()),
            ])
            ->orderBy('name')
            ->get()
            // People first, as they're what a rota is mostly for
            ->sortBy(fn (BookableResource $resource) => $resource->type->isPerson() ? 0 : 1);

        return Inertia::render('admin/bookings/rota/Index', [
            'week' => $start->toDateString(),
            'previousWeek' => $start->subWeek()->toDateString(),
            'nextWeek' => $start->addWeek()->toDateString(),
            'days' => array_map(fn (CarbonImmutable $day) => [
                'date' => $day->toDateString(),
                'day_of_week' => $day->dayOfWeekIso,
            ], $days),
            'resources' => $resources->map(fn (BookableResource $resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'type_label' => $resource->type->label(),
                'schedule' => collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [
                    $day->toDateString() => $schedule->on($resource, $day),
                ]),
            ])->values(),
            'canManage' => $request->user()?->can('create', BookableResource::class) ?? false,
        ]);
    }

    /**
     * Replaces a date's shifts. No shifts puts the date back to the weekly working hours.
     */
    public function update(RotaUpdateRequest $request): RedirectResponse
    {
        /** @var list<array{starts_at: string, ends_at: string, note?: string|null}> $shifts */
        $shifts = $request->validated('shifts') ?? [];
        $resourceId = (int) $request->validated('resource_id');
        $date = (string) $request->validated('date');

        DB::transaction(function () use ($shifts, $resourceId, $date) {
            Shift::query()->where('resource_id', $resourceId)->whereDate('works_on', $date)->delete();

            foreach ($shifts as $shift) {
                Shift::query()->create([
                    'resource_id' => $resourceId,
                    'works_on' => $date,
                    'starts_at' => $shift['starts_at'],
                    'ends_at' => $shift['ends_at'],
                    'note' => $shift['note'] ?? null,
                ]);
            }
        });

        return redirect()->back()->with('success', __('bookings.rota.flash.updated'));
    }
}
