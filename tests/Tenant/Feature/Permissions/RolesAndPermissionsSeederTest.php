<?php

use App\Models\RolePermissionExclusion;
use App\Models\User;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates all roles and permissions from the registry', function () {
    // Run the seeder
    $this->seed(RolesAndPermissionsSeeder::class);

    // Verify roles exist
    foreach (RoleEnum::cases() as $roleEnum) {
        expect(Role::where('name', $roleEnum->value)->exists())->toBeTrue();
    }

    // Verify core permissions exist
    foreach (CorePermission::cases() as $permissionEnum) {
        expect(Permission::where('name', $permissionEnum->value)->exists())->toBeTrue();
    }
});

it('assigns default permissions to roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::findByName(RoleEnum::Admin->value);

    // Admin should have all core permissions
    foreach (CorePermission::cases() as $permissionEnum) {
        expect($adminRole->hasPermissionTo($permissionEnum->value))->toBeTrue();
    }

    $viewerRole = Role::findByName(RoleEnum::Viewer->value);

    // Viewer should have no permissions
    expect($viewerRole->hasPermissionTo(CorePermission::ViewPermissions->value))->toBeFalse();
    expect($viewerRole->hasPermissionTo(CorePermission::ViewUsers->value))->toBeFalse();
    expect($viewerRole->hasPermissionTo(CorePermission::ManagePermissions->value))->toBeFalse();
    expect($viewerRole->hasPermissionTo(CorePermission::ManageUsers->value))->toBeFalse();
});

it('is idempotent and does not create duplicates', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $initialRoleCount = Role::count();
    $initialPermissionCount = Permission::count();

    // Run again
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe($initialRoleCount);
    expect(Permission::count())->toBe($initialPermissionCount);
});

it('respects exclusion rows and does not re-assign excluded permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::findByName(RoleEnum::Admin->value);
    $permission = Permission::findByName(CorePermission::ManageUsers->value);

    // Verify it has it initially
    expect($adminRole->hasPermissionTo($permission->name))->toBeTrue();

    // Remove it and add an exclusion
    $adminRole->revokePermissionTo($permission->name);
    RolePermissionExclusion::create([
        'role_id' => $adminRole->id,
        'permission_id' => $permission->id,
    ]);

    // Run seeder again
    $this->seed(RolesAndPermissionsSeeder::class);

    // Verify it was NOT re-assigned
    $adminRole->refresh();
    expect($adminRole->hasPermissionTo($permission->name))->toBeFalse();
});

it('allows SuperAdmin to bypass all gates', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    // SuperAdmin should pass any gate, even if the permission doesn't exist or isn't assigned
    expect(Gate::forUser($superAdmin)->allows('some-non-existent-permission'))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows(CorePermission::ManageUsers->value))->toBeTrue();
});
