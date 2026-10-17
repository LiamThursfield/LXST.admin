<?php

use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates all DJ permissions from the registry', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (DjPermission::cases() as $permission) {
        expect(Permission::where('name', $permission->value)->exists())->toBeTrue();
    }
});

it('lets admins and editors manage the DJ module, and viewers only view it', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach ([RoleEnum::Admin, RoleEnum::Editor] as $role) {
        foreach (DjPermission::cases() as $permission) {
            expect(Role::findByName($role->value)->hasPermissionTo($permission->value))->toBeTrue();
        }
    }

    $viewer = Role::findByName(RoleEnum::Viewer->value);

    expect($viewer->hasPermissionTo(DjPermission::ViewLibrary->value))->toBeTrue()
        ->and($viewer->hasPermissionTo(DjPermission::ViewGigs->value))->toBeTrue()
        ->and($viewer->hasPermissionTo(DjPermission::ViewStats->value))->toBeTrue()
        ->and($viewer->hasPermissionTo(DjPermission::ManageLibrary->value))->toBeFalse()
        ->and($viewer->hasPermissionTo(DjPermission::ManageGigs->value))->toBeFalse()
        ->and($viewer->hasPermissionTo(DjPermission::ManageRequests->value))->toBeFalse();
});
