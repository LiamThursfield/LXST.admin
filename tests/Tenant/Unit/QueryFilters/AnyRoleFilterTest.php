<?php

use App\Http\QueryFilters\AnyRoleFilter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('filters users by a single role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $query = User::query();
    $filter = new AnyRoleFilter;

    $filter($query, 'Admin', 'role');

    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($admin->id);
});

it('filters users by multiple roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $userWithoutRole = User::factory()->create();

    $query = User::query();
    $filter = new AnyRoleFilter;

    $filter($query, ['Admin', 'Viewer'], 'role');

    $results = $query->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($admin->id, $viewer->id)
        ->and($results->pluck('id')->toArray())->not->toContain($userWithoutRole->id);
});

it('returns no users if none match the role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $query = User::query();
    $filter = new AnyRoleFilter;

    $filter($query, 'Admin', 'role');

    $results = $query->get();

    expect($results)->toHaveCount(0);
});
