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
     * An admin with two-factor login on, to try the code-entry page. Add the secret to an
     * authenticator app, or sign in with one of the recovery codes.
     *
     * @var array{email: string, password: string, first_name: string, last_name: string, secret: string, recovery_codes: list<string>}
     */
    public const array TWO_FACTOR_USER = [
        'email' => 'two-factor@example.com',
        'password' => 'TwoFactorPassword',
        'first_name' => 'Tess',
        'last_name' => 'Factor',
        'secret' => 'JBSWY3DPEHPK3PXP',
        'recovery_codes' => ['seeded-recovery-1', 'seeded-recovery-2', 'seeded-recovery-3'],
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

        $this->seedTwoFactorUser();
    }

    /**
     * The admin with two-factor login on, reset to the known secret and recovery codes
     */
    private function seedTwoFactorUser(): void
    {
        $details = self::TWO_FACTOR_USER;

        $user = User::query()->updateOrCreate(
            ['email' => $details['email']],
            [
                'first_name' => $details['first_name'],
                'last_name' => $details['last_name'],
                'password' => Hash::make($details['password']),
            ],
        );
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_secret' => encrypt($details['secret']),
            'two_factor_recovery_codes' => encrypt((string) json_encode($details['recovery_codes'])),
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? now(),
        ])->save();
        $user->syncRoles([RoleEnum::Admin->value]);
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
        return [...array_values(array_column(self::USERS, 'email')), self::TWO_FACTOR_USER['email']];
    }
}
