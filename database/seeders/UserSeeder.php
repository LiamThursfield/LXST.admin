<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findByName($roleEnum->value);

            $users = User::factory()->count(8)->create();
            foreach ($users as $user) {
                $user->assignRole($role);
            }
        }
    }
}
