<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\TimeOffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\StaffHoursRequest;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\TimeOff;
use App\Services\Bookings\ResourceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;

class StaffHoursController extends Controller
{
    /**
     * Each person's scheduled hours and days off over a range, this week by default
     */
    public function __invoke(StaffHoursRequest $request, ResourceSchedule $schedule): Response
    {
        /** @var string|null $fromInput */
        $fromInput = $request->validated('from');
        /** @var string|null $toInput */
        $toInput = $request->validated('to');

        $from = $fromInput === null ? CarbonImmutable::now()->startOfWeek() : CarbonImmutable::parse($fromInput);
        $to = $toInput === null ? $from->addDays(6) : CarbonImmutable::parse($toInput);

        $people = BookableResource::query()
            ->where('type', ResourceType::Person)
            ->with([
                'workingHours',
                'shifts' => fn (HasMany $query) => $query->whereBetween('works_on', [$from->toDateString(), $to->toDateString()]),
                'timeOff' => fn (HasMany $query) => $query->where('status', TimeOffStatus::Approved)
                    ->whereDate('starts_on', '<=', $to->toDateString())
                    ->whereDate('ends_on', '>=', $from->toDateString()),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/bookings/staff-hours/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'people' => $people->map(fn (BookableResource $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'job_title' => $person->job_title,
                'hours' => round($schedule->minutesBetween($person, $from, $to) / 60, 2),
                'days_off' => $person->timeOff->sum(fn (TimeOff $timeOff) => $this->daysWithin($timeOff, $from, $to)),
            ])->values(),
        ]);
    }

    protected function daysWithin(TimeOff $timeOff, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = max($timeOff->starts_on->toDateString(), $from->toDateString());
        $end = min($timeOff->ends_on->toDateString(), $to->toDateString());

        return (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
    }
}
