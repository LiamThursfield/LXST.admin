<?php

namespace Tests\Unit\Tracker;

use App\Data\Tracker\TaskSchedule;
use Carbon\Carbon;

$wednesday = Carbon::parse('2026-07-29'); // isoWeekday 3, July
$tuesday = Carbon::parse('2026-07-28');   // isoWeekday 2, July
$september = Carbon::parse('2026-09-15'); // isoWeekday 2, September

describe('matches', function () use ($wednesday, $tuesday, $september) {
    it('matches every date when no filters are set', function () use ($wednesday, $tuesday) {
        $schedule = new TaskSchedule;

        expect($schedule->matches($wednesday))->toBeTrue()
            ->and($schedule->matches($tuesday))->toBeTrue();
    });

    it('filters by day of week', function () use ($wednesday, $tuesday) {
        $schedule = new TaskSchedule(daysOfWeek: [1, 3, 5]); // Mon/Wed/Fri

        expect($schedule->matches($wednesday))->toBeTrue()
            ->and($schedule->matches($tuesday))->toBeFalse();
    });

    it('filters by month of year', function () use ($wednesday, $september) {
        $schedule = new TaskSchedule(monthsOfYear: [6, 7, 8]); // Jun/Jul/Aug

        expect($schedule->matches($wednesday))->toBeTrue()
            ->and($schedule->matches($september))->toBeFalse();
    });

    it('requires every present filter to pass', function () use ($wednesday, $tuesday, $september) {
        $schedule = new TaskSchedule(daysOfWeek: [1, 3, 5], monthsOfYear: [6, 7, 8]);

        // Wednesday in July: passes both.
        expect($schedule->matches($wednesday))->toBeTrue()
            // Tuesday in July: right month, wrong day.
            ->and($schedule->matches($tuesday))->toBeFalse()
            // Tuesday in September: wrong on both.
            ->and($schedule->matches($september))->toBeFalse();
    });

    it('filters by day of month', function () use ($wednesday) {
        expect((new TaskSchedule(daysOfMonth: [29]))->matches($wednesday))->toBeTrue()
            ->and((new TaskSchedule(daysOfMonth: [1, 15]))->matches($wednesday))->toBeFalse();
    });
});
