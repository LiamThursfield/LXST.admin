<?php

namespace Database\Seeders;

use App\Models\RolePermissionExclusion;
use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $registry = app(AuthorisationRegistry::class);

        // 1. Create all permissions
        foreach ($registry->allPermissions() as $permissionEnum) {
            Permission::findOrCreate($permissionEnum->value);
        }

        // 2. Create all roles and assign default permissions
        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value);

            // SuperAdmin bypass is handled in AuthorisationServiceProvider::boot via Gate::before
            if ($roleEnum === RoleEnum::SuperAdmin) {
                continue;
            }

            $defaultPermissions = $registry->permissionsForRole($roleEnum);

            foreach ($defaultPermissions as $permissionEnum) {
                // Check if there is an exclusion for this role-permission pair
                $isExcluded = RolePermissionExclusion::where('role_id', $role->id)
                    ->where('permission_id', Permission::findByName($permissionEnum->value)->id)
                    ->exists();

                if (! $isExcluded) {
                    $role->givePermissionTo($permissionEnum->value);
                }
            }
        }
    }
}
