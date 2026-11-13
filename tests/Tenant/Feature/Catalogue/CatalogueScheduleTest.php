<?php

use App\Models\Catalogue\Catalogue;
use Carbon\CarbonImmutable;

// 2026-11-16 is a Monday
test('a catalogue with no schedule applies whenever it is published', function () {
    $catalogue = Catalogue::factory()->make();

    expect($catalogue->appliesAt(CarbonImmutable::parse('2026-11-16 03:00')))->toBeTrue()
        ->and($catalogue->scheduleSummary())->toBeNull()
        ->and(Catalogue::factory()->unpublished()->make()->appliesAt(CarbonImmutable::parse('2026-11-16 03:00')))->toBeFalse();
});

test('a catalogue applies only on its days and between its times', function () {
    $lunch = Catalogue::factory()->availableAt([1, 2, 3, 4, 5], '12:00:00', '15:00:00')->make();

    expect($lunch->appliesAt(CarbonImmutable::parse('2026-11-16 12:00')))->toBeTrue()
        ->and($lunch->appliesAt(CarbonImmutable::parse('2026-11-16 14:59')))->toBeTrue()
        ->and($lunch->appliesAt(CarbonImmutable::parse('2026-11-16 15:00')))->toBeFalse()
        ->and($lunch->appliesAt(CarbonImmutable::parse('2026-11-16 11:59')))->toBeFalse()
        // Saturday
        ->and($lunch->appliesAt(CarbonImmutable::parse('2026-11-21 13:00')))->toBeFalse()
        ->and($lunch->scheduleSummary())->toBe('Mon, Tue, Wed, Thu, Fri · 12:00–15:00');
});

test('a time range past midnight belongs to the day it started on', function () {
    // Friday and Saturday nights
    $lateNight = Catalogue::factory()->availableAt([5, 6], '22:00:00', '02:00:00')->make();

    expect($lateNight->appliesAt(CarbonImmutable::parse('2026-11-20 23:00')))->toBeTrue()
        // Early Saturday is still Friday night
        ->and($lateNight->appliesAt(CarbonImmutable::parse('2026-11-21 01:30')))->toBeTrue()
        // Early Monday is Sunday night, which isn't one of its days
        ->and($lateNight->appliesAt(CarbonImmutable::parse('2026-11-23 01:30')))->toBeFalse()
        ->and($lateNight->appliesAt(CarbonImmutable::parse('2026-11-21 02:00')))->toBeFalse()
        ->and($lateNight->appliesAt(CarbonImmutable::parse('2026-11-20 21:59')))->toBeFalse();
});

test('a seasonal catalogue applies only between its dates', function () {
    $festive = Catalogue::factory()->seasonal('2026-12-01', '2026-12-31')->make();

    expect($festive->appliesAt(CarbonImmutable::parse('2026-11-30 12:00')))->toBeFalse()
        ->and($festive->appliesAt(CarbonImmutable::parse('2026-12-01 00:00')))->toBeTrue()
        ->and($festive->appliesAt(CarbonImmutable::parse('2026-12-31 23:59')))->toBeTrue()
        ->and($festive->appliesAt(CarbonImmutable::parse('2027-01-01 00:00')))->toBeFalse()
        ->and($festive->scheduleSummary())->toBe('1 Dec 2026 to 31 Dec 2026');
});
