<?php

namespace Tests\Unit\Tracker;

use App\Enums\Tracker\TrackerMeasure;
use App\Enums\Tracker\TrackerUnit;

describe('measure', function () {
    it('maps every unit to its measure', function () {
        expect(TrackerUnit::Boolean->measure())->toBe(TrackerMeasure::Boolean)
            ->and(TrackerUnit::Count->measure())->toBe(TrackerMeasure::Count)
            ->and(TrackerUnit::Kilograms->measure())->toBe(TrackerMeasure::Weight)
            ->and(TrackerUnit::Pounds->measure())->toBe(TrackerMeasure::Weight)
            ->and(TrackerUnit::Litres->measure())->toBe(TrackerMeasure::Volume)
            ->and(TrackerUnit::Millilitres->measure())->toBe(TrackerMeasure::Volume)
            ->and(TrackerUnit::Minutes->measure())->toBe(TrackerMeasure::Duration)
            ->and(TrackerUnit::Hours->measure())->toBe(TrackerMeasure::Duration)
            ->and(TrackerUnit::Kilocalories->measure())->toBe(TrackerMeasure::Energy)
            ->and(TrackerUnit::TimeOfDay->measure())->toBe(TrackerMeasure::ClockTime);
    });
});

describe('toBase', function () {
    it('converts display values into the canonical base unit', function () {
        expect(TrackerUnit::Litres->toBase(2.0))->toBe(2000.0)
            ->and(TrackerUnit::Minutes->toBase(30.0))->toBe(1800.0)
            ->and(TrackerUnit::Hours->toBase(1.5))->toBe(5400.0)
            ->and(TrackerUnit::Kilograms->toBase(80.0))->toBe(80000.0)
            ->and(TrackerUnit::Millilitres->toBase(250.0))->toBe(250.0)
            ->and(TrackerUnit::Kilocalories->toBase(1500.0))->toBe(1500.0);
    });
});

describe('fromBase', function () {
    it('round-trips through toBase for every unit', function (TrackerUnit $unit) {
        expect($unit->fromBase($unit->toBase(42.0)))->toBe(42.0);
    })->with(TrackerUnit::cases());
});
