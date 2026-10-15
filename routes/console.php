<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tracker: create today's occurrences, then close out any whose period has ended.
// Hourly so each user's local midnight is processed promptly (both are idempotent).
Schedule::command('tracker:generate-occurrences')->hourlyAt(5)->withoutOverlapping();
Schedule::command('tracker:evaluate-occurrences')->hourlyAt(15)->withoutOverlapping();
