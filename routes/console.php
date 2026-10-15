<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tracker: create today's occurrences, then close out any whose period has ended.
Schedule::command('tracker:generate-occurrences')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('tracker:evaluate-occurrences')->dailyAt('00:15')->withoutOverlapping();
