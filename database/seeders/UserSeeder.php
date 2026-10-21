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
        // Safe to re-run: the bulk users are only made once, alongside the role users
        if (User::query()->whereNotIn('email', RoleUserSeeder::emails())->exists()) {
            $this->command?->warn('Users already exist; skipping.');

            return;
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findByName($roleEnum->value);

            $users = User::factory()->count(8)->create();
            foreach ($users as $user) {
                $user->assignRole($role);
            }
        }
    }
}
