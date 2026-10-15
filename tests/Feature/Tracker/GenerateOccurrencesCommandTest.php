<?php

use App\Models\Tenant;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('generates occurrences for a tenants active definitions', function () {
    $tenant = Tenant::create(['id' => 't'.uniqid(), 'name' => 'Tenant']);

    $tenant->run(function () {
        $user = User::factory()->create();

        TaskDefinition::factory()->forUser($user)->create([
            'period' => 'daily',
            'active_from' => now()->subDays(2)->toDateString(),
            'active_to' => null,
            'schedule' => null,
            'status' => 'active',
        ]);
    });

    $exitCode = Artisan::call('tracker:generate-occurrences', ['--tenant' => $tenant->id]);

    expect($exitCode)->toBe(0);

    $tenant->run(function () {
        // today-2, today-1, today.
        expect(TaskOccurrence::count())->toBeGreaterThanOrEqual(3);
    });
});
