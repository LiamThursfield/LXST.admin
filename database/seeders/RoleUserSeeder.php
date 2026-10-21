<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One known user per role, to sign in as after a tenant is reset (#164). These are local test
 * accounts: `tenants:refresh` refuses to run in production.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=RoleUserSeeder`. Safe to re-run:
 * each user is updated (password and role reset) rather than duplicated.
 */
class RoleUserSeeder extends Seeder
{
    /**
     * @var array<string, array{email: string, password: string, first_name: string, last_name: string}>
     */
    public const array USERS = [
        RoleEnum::SuperAdmin->value => ['email' => 'superadmin@example.com', 'password' => 'SuperAdminPassword', 'first_name' => 'Super', 'last_name' => 'Admin'],
        RoleEnum::Admin->value => ['email' => 'admin@example.com', 'password' => 'AdminPassword', 'first_name' => 'Ada', 'last_name' => 'Admin'],
        RoleEnum::Editor->value => ['email' => 'editor@example.com', 'password' => 'EditorPassword', 'first_name' => 'Eddie', 'last_name' => 'Editor'],
        RoleEnum::Viewer->value => ['email' => 'viewer@example.com', 'password' => 'ViewerPassword', 'first_name' => 'Vic', 'last_name' => 'Viewer'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::USERS as $role => $details) {
            $user = User::query()->updateOrCreate(
                ['email' => $details['email']],
                [
                    'first_name' => $details['first_name'],
                    'last_name' => $details['last_name'],
                    'password' => Hash::make($details['password']),
                ],
            );
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->syncRoles([$role]);
        }
    }

    /**
     * The user for the role, once seeded
     */
    public static function userFor(RoleEnum $role): ?User
    {
        return User::query()->where('email', self::USERS[$role->value]['email'])->first();
    }

    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        return array_values(array_column(self::USERS, 'email'));
    }
}
