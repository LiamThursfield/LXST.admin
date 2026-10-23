<?php

use App\Enums\TenantModule;
use App\Models\Cms\Metadata;
use App\Models\Crm\Contact;
use App\Models\FileManager\File;
use App\Models\Settings\ThemeSettings;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\ApiTokenSeeder;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('file_manager');
});

/**
 * How many rows each of the tenant's tables has
 *
 * @return array<string, int>
 */
function tenantRowCounts(): array
{
    return collect(Schema::getTables(DB::connection()->getDatabaseName()))
        ->pluck('name')
        ->reject(fn (string $table) => in_array($table, ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'], true))
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
        ->all();
}

test('the tenant seeder makes a known user per role, more users, and each module\'s data', function () {
    tenant()->update(['modules' => TenantModule::cases()]);

    $this->seed(TenantDatabaseSeeder::class);

    foreach (RoleUserSeeder::USERS as $role => $details) {
        $user = User::query()->where('email', $details['email'])->sole();

        expect($user->hasRole($role))->toBeTrue()
            ->and(Hash::check($details['password'], $user->password))->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();
    }

    expect(User::count())->toBeGreaterThan(count(RoleUserSeeder::USERS))
        ->and(Contact::count())->toBeGreaterThan(0)
        ->and(TaskDefinition::ownedBy(RoleUserSeeder::userFor(RoleEnum::Admin))->count())->toBeGreaterThan(0)
        // Files come first, so the theme and the CMS can use them
        ->and(app(ThemeSettings::class)->logo_file_id)->toBe(File::query()->where('path', 'branding/logo.png')->value('id'))
        ->and(Metadata::query()->whereNotNull('og_image_file_id')->exists())->toBeTrue()
        ->and(ApiTokenSeeder::token()->tokenable->is(RoleUserSeeder::userFor(RoleEnum::Admin)))->toBeTrue();
});

test('running the tenant seeder again creates nothing new', function () {
    tenant()->update(['modules' => TenantModule::cases()]);

    $this->seed(TenantDatabaseSeeder::class);
    $first = tenantRowCounts();

    $this->seed(TenantDatabaseSeeder::class);

    expect(tenantRowCounts())->toBe($first);
});

test('module seeders only run for the modules the tenant has', function () {
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->seed(TenantDatabaseSeeder::class);

    expect(Contact::count())->toBe(0)
        ->and(TaskDefinition::count())->toBe(0)
        ->and(RoleUserSeeder::userFor(RoleEnum::Admin))->not->toBeNull();
});

test('a role user\'s password and role are put back if they were changed', function () {
    $this->seed(RoleUserSeeder::class);
    $admin = RoleUserSeeder::userFor(RoleEnum::Admin);
    $admin->update(['password' => Hash::make('changed')]);
    $admin->syncRoles([RoleEnum::Viewer->value]);

    $this->seed(RoleUserSeeder::class);

    expect(Hash::check('AdminPassword', $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->hasRole(RoleEnum::Admin->value))->toBeTrue()
        ->and(User::query()->where('email', 'admin@example.com')->count())->toBe(1);
});
