<?php

use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Authorisation\Enums\TrackerPermission;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates all tracker permissions from the registry', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (TrackerPermission::cases() as $permissionEnum) {
        expect(Permission::where('name', $permissionEnum->value)->exists())->toBeTrue();
    }
});

it('assigns all tracker permissions to admin, including managing public categories', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::findByName(RoleEnum::Admin->value);

    foreach (TrackerPermission::cases() as $permissionEnum) {
        expect($adminRole->hasPermissionTo($permissionEnum->value))->toBeTrue();
    }
});

it('does not assign managing public categories to editor or viewer', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $editorRole = Role::findByName(RoleEnum::Editor->value);
    $viewerRole = Role::findByName(RoleEnum::Viewer->value);

    expect($editorRole->hasPermissionTo(TrackerPermission::ManagePublicCategories->value))->toBeFalse();
    expect($viewerRole->hasPermissionTo(TrackerPermission::ManagePublicCategories->value))->toBeFalse();
});

it('assigns editor and viewer full control of their own tracker data', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $personalPermissions = [
        TrackerPermission::ViewTasks,
        TrackerPermission::ManageTasks,
        TrackerPermission::LogEntries,
        TrackerPermission::ViewCategories,
        TrackerPermission::ManageCategories,
        TrackerPermission::ManageHolidays,
    ];

    $editorRole = Role::findByName(RoleEnum::Editor->value);
    $viewerRole = Role::findByName(RoleEnum::Viewer->value);

    foreach ($personalPermissions as $permissionEnum) {
        expect($editorRole->hasPermissionTo($permissionEnum->value))->toBeTrue();
        expect($viewerRole->hasPermissionTo($permissionEnum->value))->toBeTrue();
    }
});
