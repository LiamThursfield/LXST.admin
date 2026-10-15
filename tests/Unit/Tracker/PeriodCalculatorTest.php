<?php

namespace Tests\Unit\Tracker;

use App\Enums\Tracker\TrackerPeriod;
use App\Services\Tracker\PeriodCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

function calc(): PeriodCalculator
{
    return new PeriodCalculator;
}

function windowFor(TrackerPeriod $period, string $date): array
{
    $w = calc()->window($period, CarbonImmutable::parse($date));

    return [
        'key' => $w->key,
        'start' => $w->start->toDateString(),
        'end' => $w->end?->toDateString(),
    ];
}

describe('daily window', function () {
    it('is a single day keyed by the date', function () {
        expect(windowFor(TrackerPeriod::Daily, '2026-07-23'))->toBe([
            'key' => '2026-07-23',
            'start' => '2026-07-23',
            'end' => '2026-07-23',
        ]);
    });
});

describe('weekly window', function () {
    it('spans Monday to Sunday keyed by the ISO week', function () {
        expect(windowFor(TrackerPeriod::Weekly, '2026-07-23'))->toBe([
            'key' => '2026-W30',
            'start' => '2026-07-20',
            'end' => '2026-07-26',
        ]);
    });

    it('uses the ISO week-numbering year at the end of December', function () {
        // 2027-01-01 belongs to ISO week 53 of 2026.
        expect(windowFor(TrackerPeriod::Weekly, '2027-01-01'))->toBe([
            'key' => '2026-W53',
            'start' => '2026-12-28',
            'end' => '2027-01-03',
        ]);
    });

    it('uses the ISO week-numbering year at the start of January', function () {
        // 2026-01-01 is ISO week 1 of 2026, whose Monday is in December 2025.
        expect(windowFor(TrackerPeriod::Weekly, '2026-01-01'))->toBe([
            'key' => '2026-W01',
            'start' => '2025-12-29',
            'end' => '2026-01-04',
        ]);
    });
});

describe('monthly window', function () {
    it('spans the calendar month', function () {
        expect(windowFor(TrackerPeriod::Monthly, '2026-07-23'))->toBe([
            'key' => '2026-07',
            'start' => '2026-07-01',
            'end' => '2026-07-31',
        ]);
    });
});

describe('yearly window', function () {
    it('spans the calendar year', function () {
        expect(windowFor(TrackerPeriod::Yearly, '2026-07-23'))->toBe([
            'key' => '2026',
            'start' => '2026-01-01',
            'end' => '2026-12-31',
        ]);
    });
});

describe('next', function () {
    it('advances by one window', function () {
        $c = calc();

        expect($c->next(TrackerPeriod::Daily, CarbonImmutable::parse('2026-07-23'))->toDateString())->toBe('2026-07-24')
            ->and($c->next(TrackerPeriod::Weekly, CarbonImmutable::parse('2026-07-20'))->toDateString())->toBe('2026-07-27')
            ->and($c->next(TrackerPeriod::Monthly, CarbonImmutable::parse('2026-07-01'))->toDateString())->toBe('2026-08-01')
            ->and($c->next(TrackerPeriod::Yearly, CarbonImmutable::parse('2026-01-01'))->toDateString())->toBe('2027-01-01');
    });
});

describe('non-recurring periods', function () {
    it('throws when asked for a lifetime or one-off window', function () {
        expect(fn () => calc()->window(TrackerPeriod::Lifetime, CarbonImmutable::now()))
            ->toThrow(InvalidArgumentException::class);
    });

    it('reports which periods are recurring', function () {
        $c = calc();

        expect($c->isRecurring(TrackerPeriod::Daily))->toBeTrue()
            ->and($c->isRecurring(TrackerPeriod::Weekly))->toBeTrue()
            ->and($c->isRecurring(TrackerPeriod::Monthly))->toBeTrue()
            ->and($c->isRecurring(TrackerPeriod::Yearly))->toBeTrue()
            ->and($c->isRecurring(TrackerPeriod::Lifetime))->toBeFalse()
            ->and($c->isRecurring(TrackerPeriod::OneOff))->toBeFalse();
    });
});
