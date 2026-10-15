<?php

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Tracker\OccurrenceEvaluator;
use App\Services\Tracker\OccurrenceGenerator;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A tracker user living in the given timezone (null = app timezone, UTC).
 */
function userIn(?string $timezone): User
{
    return test()->createUserWithPermissions(
        [TrackerPermission::ViewTasks, TrackerPermission::LogEntries],
        ['timezone' => $timezone],
    );
}

/**
 * A daily "drink at least 2 litres" task (stored in ml).
 */
function dailyTask(User $user): TaskDefinition
{
    return TaskDefinition::factory()->forUser($user)->create([
        'active_from' => '2026-08-01',
        'unit' => 'l',
        'target_min' => 2000,
        'stepped' => true,
        'schedule' => null,
    ]);
}

function pendingOccurrence(TaskDefinition $task, string $day): TaskOccurrence
{
    return TaskOccurrence::factory()->forDefinition($task)->create([
        'period_key' => $day,
        'period_start' => $day,
        'period_end' => $day,
        'status' => TaskOccurrenceStatus::Pending,
        'is_holiday' => false,
    ]);
}

test('users without a timezone fall back to the app timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-04 23:30'));

    $user = userIn(null);

    expect($user->localTimezone())->toBe('UTC')
        ->and($user->localToday()->toDateString())->toBe('2026-08-04');
});

test('an entry logged just after local midnight is filed under the local day and stored in UTC', function () {
    // 00:30 BST on 2026-08-05.
    $this->travelTo(CarbonImmutable::parse('2026-08-04 23:30'));

    $user = userIn('Europe/London');
    $task = dailyTask($user);

    $this->actingAs($user)
        ->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 0.5])
        ->assertSessionHasNoErrors();

    $entry = TaskEntry::sole();

    expect($entry->taskOccurrence->period_key)->toBe('2026-08-05')
        ->and($entry->getRawOriginal('occurred_at'))->toBe('2026-08-04 23:30:00');
});

test('a submitted occurred_at is interpreted as the users local wall time', function () {
    // 13:00 BST on 2026-08-05.
    $this->travelTo(CarbonImmutable::parse('2026-08-05 12:00'));

    $user = userIn('Europe/London');
    $task = dailyTask($user);

    $this->actingAs($user)
        ->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 0.5, 'occurred_at' => '2026-08-05 00:30'])
        ->assertSessionHasNoErrors();

    // 12:30 local is in the past for a London user, though later than UTC now.
    $this->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 0.5, 'occurred_at' => '2026-08-05 12:30'])
        ->assertSessionHasNoErrors();

    $this->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 0.5, 'occurred_at' => '2026-08-05 13:30'])
        ->assertSessionHasErrors(['occurred_at']);

    expect(TaskEntry::orderBy('occurred_at')->pluck('occurred_at')->map->toDateTimeString()->all())
        ->toBe(['2026-08-04 23:30:00', '2026-08-05 11:30:00'])
        ->and(TaskOccurrence::pluck('period_key')->all())->toBe(['2026-08-05']);
});

test('the today page uses the users local date around midnight', function (string $utcNow, ?string $timezone, string $localToday) {
    $this->travelTo(CarbonImmutable::parse($utcNow));

    $user = userIn($timezone);
    dailyTask($user);

    $this->actingAs($user)
        ->get(route('admin.tracker.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', $localToday)
            ->where('today', $localToday)
            ->where('occurrences.data.0.period_key', $localToday)
        );
})->with([
    'ahead of UTC (Auckland, already tomorrow)' => ['2026-08-05 13:00', 'Pacific/Auckland', '2026-08-06'],
    'behind UTC (Los Angeles, still yesterday)' => ['2026-08-05 03:00', 'America/Los_Angeles', '2026-08-04'],
    'no timezone (UTC)' => ['2026-08-05 03:00', null, '2026-08-05'],
]);

test('the today page rejects dates after the users local today', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-05 13:00'));

    $this->actingAs(userIn('Pacific/Auckland'))
        ->get(route('admin.tracker.today', ['date' => '2026-08-06']))
        ->assertOk();

    $this->actingAs(userIn('America/Los_Angeles'))
        ->get(route('admin.tracker.today', ['date' => '2026-08-06']))
        ->assertSessionHasErrors(['date']);
});

test('a day closes at the owners local midnight, not UTC midnight', function () {
    // 00:30 BST on 2026-08-05, but still 16:30 on 2026-08-04 in Los Angeles and 23:30 in UTC.
    $this->travelTo(CarbonImmutable::parse('2026-08-04 23:30'));

    $london = pendingOccurrence(dailyTask(userIn('Europe/London')), '2026-08-04');
    $losAngeles = pendingOccurrence(dailyTask(userIn('America/Los_Angeles')), '2026-08-04');
    $utc = pendingOccurrence(dailyTask(userIn(null)), '2026-08-04');

    expect(app(OccurrenceEvaluator::class)->evaluateDueForTenant())->toBe(1)
        ->and($london->fresh()->status)->toBe(TaskOccurrenceStatus::Missed)
        ->and($losAngeles->fresh()->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($utc->fresh()->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('evaluating without a day uses the owners local today', function () {
    // Already 2026-08-05 in UTC, but 20:00 on 2026-08-04 in Los Angeles.
    $this->travelTo(CarbonImmutable::parse('2026-08-05 03:00'));

    $occurrence = pendingOccurrence(dailyTask(userIn('America/Los_Angeles')), '2026-08-04');

    expect(app(OccurrenceEvaluator::class)->evaluate($occurrence)->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('generation runs up to each owners local today', function () {
    // 01:00 on 2026-08-06 in Auckland, 13:00 on 2026-08-05 in UTC.
    $this->travelTo(CarbonImmutable::parse('2026-08-05 13:00'));

    $auckland = dailyTask(userIn('Pacific/Auckland'));
    $utc = dailyTask(userIn(null));

    app(OccurrenceGenerator::class)->generateForTenant();

    expect(TaskOccurrence::where('task_definition_id', $auckland->id)->max('period_key'))->toBe('2026-08-06')
        ->and(TaskOccurrence::where('task_definition_id', $utc->id)->max('period_key'))->toBe('2026-08-05');
});

test('changing timezone does not move existing entries between occurrences', function () {
    // 00:30 BST on 2026-08-05.
    $this->travelTo(CarbonImmutable::parse('2026-08-04 23:30'));

    $user = userIn('Europe/London');
    $task = dailyTask($user);

    $this->actingAs($user)
        ->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 2])
        ->assertSessionHasNoErrors();

    $entry = TaskEntry::sole();
    $occurrenceId = $entry->task_occurrence_id;

    // In New York the entry's instant is 19:30 on 2026-08-04.
    $user->update(['timezone' => 'America/New_York']);
    $this->travelTo(CarbonImmutable::parse('2026-08-06 12:00'));

    $this->get(route('admin.tracker.today', ['date' => '2026-08-05']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.data.0.period_key', '2026-08-05')
            ->where('occurrences.data.0.status', 'met')
            ->has('occurrences.data.0.entries', 1)
        );

    $this->get(route('admin.tracker.today', ['date' => '2026-08-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.data.0.period_key', '2026-08-04')
            ->has('occurrences.data.0.entries', 0)
        );

    expect($entry->fresh()->task_occurrence_id)->toBe($occurrenceId);
});

test('time-of-day values are wall-clock and unaffected by timezone', function (?string $timezone) {
    $this->travelTo(CarbonImmutable::parse('2026-08-05 12:00'));

    $user = userIn($timezone);

    // "Wake up by 06:30" (seconds since local midnight).
    $task = TaskDefinition::factory()->forUser($user)->create([
        'active_from' => '2026-08-01',
        'measure' => 'clock-time',
        'unit' => 'time-of-day',
        'comparator' => 'max',
        'aggregation' => 'last',
        'target_min' => null,
        'target_max' => 23400,
        'stepped' => false,
        'schedule' => null,
    ]);

    $this->actingAs($user)
        ->post(route('admin.tracker.tasks.entries.store', $task), ['value' => 22500, 'occurred_at' => '2026-08-03 06:20']) // 06:15
        ->assertSessionHasNoErrors();

    $occurrence = TaskEntry::sole()->taskOccurrence;

    expect($occurrence->period_key)->toBe('2026-08-03')
        ->and((float) $occurrence->computed_value)->toBe(22500.0)
        ->and($occurrence->status)->toBe(TaskOccurrenceStatus::Met);
})->with([
    'UTC' => [null],
    'Europe/London' => ['Europe/London'],
    'Pacific/Auckland' => ['Pacific/Auckland'],
    'America/Los_Angeles' => ['America/Los_Angeles'],
]);
