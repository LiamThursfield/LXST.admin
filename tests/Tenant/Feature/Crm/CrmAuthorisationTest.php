<?php

use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates all CRM permissions from the registry', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (CrmPermission::cases() as $permission) {
        expect(Permission::where('name', $permission->value)->exists())->toBeTrue();
    }
});

it('lets admins and editors manage the CRM, and viewers only view it', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach ([RoleEnum::Admin, RoleEnum::Editor] as $role) {
        expect(Role::findByName($role->value)->hasPermissionTo(CrmPermission::ManageOrganisationUnits->value))->toBeTrue();
    }

    $viewer = Role::findByName(RoleEnum::Viewer->value);

    expect($viewer->hasPermissionTo(CrmPermission::ViewOrganisationUnits->value))->toBeTrue()
        ->and($viewer->hasPermissionTo(CrmPermission::ManageOrganisationUnits->value))->toBeFalse();
});
