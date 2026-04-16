<?php

use App\Models\Tenant;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

it('runs the sync command for all tenants', function () {
    $id1 = 't'.uniqid();
    $id2 = 't'.uniqid();

    // Create two tenants
    $tenant1 = Tenant::create(['id' => $id1, 'name' => 'Tenant 1']);
    $tenant2 = Tenant::create(['id' => $id2, 'name' => 'Tenant 2']);

    // Run the sync command
    $exitCode = Artisan::call('permissions:sync');

    expect($exitCode)->toBe(0);

    // Verify seeder ran for both (checking if roles exist in each)
    $tenant1->run(function () {
        expect(Role::where('name', RoleEnum::Admin->value)->exists())->toBeTrue();
    });

    $tenant2->run(function () {
        expect(Role::where('name', RoleEnum::Admin->value)->exists())->toBeTrue();
    });
});

it('runs the sync command for a specific tenant', function () {
    $id3 = 't'.uniqid();
    $id4 = 't'.uniqid();

    $tenant3 = Tenant::create(['id' => $id3, 'name' => 'Tenant 3']);
    $tenant4 = Tenant::create(['id' => $id4, 'name' => 'Tenant 4']);

    // Run only for id3
    $exitCode = Artisan::call('permissions:sync', ['--tenant' => $id3]);

    expect($exitCode)->toBe(0);

    // id3 should have roles
    $tenant3->run(function () {
        expect(Role::where('name', RoleEnum::Admin->value)->exists())->toBeTrue();
    });
});
