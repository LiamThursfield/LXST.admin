<?php

use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\TenancyBootstrapped;

describe('general settings', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
    });

    test('the site name starts as the tenant\'s name', function () {
        $this->get(route('admin.site-settings.general.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/site-settings/General')
                ->where('settings.site_name', tenant('name'))
                ->where('canManage', true)
            );
    });

    test('the site name can be changed', function () {
        $this->put(route('admin.site-settings.general.update'), ['site_name' => 'Acme Ltd'])
            ->assertRedirect(route('admin.site-settings.general.edit'))
            ->assertSessionHas('success', __('site_settings.general.updated'));

        expect(app(GeneralSettings::class)->site_name)->toBe('Acme Ltd');
    });

    test('the site name is required', function () {
        $this->put(route('admin.site-settings.general.update'), ['site_name' => ''])
            ->assertSessionHasErrors(['site_name' => __('site_settings.general.validation.site_name_required')]);
    });
});

describe('integration settings', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
    });

    test('the GTM container ID is saved in upper case', function () {
        $this->put(route('admin.site-settings.integrations.update'), ['gtm_id' => ' gtm-abc1234 '])
            ->assertRedirect(route('admin.site-settings.integrations.edit'));

        expect(app(IntegrationSettings::class)->gtm_id)->toBe('GTM-ABC1234');

        $this->get(route('admin.site-settings.integrations.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.gtm_id', 'GTM-ABC1234'));
    });

    test('clearing the GTM container ID turns it off', function () {
        $settings = app(IntegrationSettings::class);
        $settings->gtm_id = 'GTM-ABC1234';
        $settings->save();

        $this->put(route('admin.site-settings.integrations.update'), ['gtm_id' => '']);

        expect(app(IntegrationSettings::class)->gtm_id)->toBeNull();
    });

    test('anything that isn\'t a container ID is rejected', function () {
        $this->put(route('admin.site-settings.integrations.update'), ['gtm_id' => 'UA-12345-1'])
            ->assertSessionHasErrors(['gtm_id' => __('site_settings.integrations.validation.gtm_id_format')]);
    });
});

describe('access', function () {
    test('users who can only view settings see them read-only and cannot change them', function () {
        $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));

        $this->get(route('admin.site-settings.general.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->put(route('admin.site-settings.general.update'), ['site_name' => 'Nope'])->assertForbidden();
        $this->put(route('admin.site-settings.integrations.update'), ['gtm_id' => 'GTM-ABC1234'])->assertForbidden();
    });

    test('users without settings permissions cannot see settings, or the menu item', function () {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user);

        $this->get(route('admin.site-settings.general.edit'))->assertForbidden();
        $this->get(route('admin.site-settings.integrations.edit'))->assertForbidden();

        expect(siteSettingsMenuIsVisible($user))->toBeFalse()
            ->and(siteSettingsMenuIsVisible($this->createUserWithPermissions([SettingsPermission::ViewSettings])))->toBeTrue();
    });

    test('admins can manage settings, editors can only view them, and viewers cannot see them', function () {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = Role::findByName(RoleEnum::Admin->value);
        $editor = Role::findByName(RoleEnum::Editor->value);
        $viewer = Role::findByName(RoleEnum::Viewer->value);

        expect($admin->hasPermissionTo(SettingsPermission::ManageSettings->value))->toBeTrue()
            ->and($editor->hasPermissionTo(SettingsPermission::ViewSettings->value))->toBeTrue()
            ->and($editor->hasPermissionTo(SettingsPermission::ManageSettings->value))->toBeFalse()
            ->and($viewer->hasPermissionTo(SettingsPermission::ViewSettings->value))->toBeFalse();
    });
});

describe('tenancy', function () {
    test('settings are stored in the tenant\'s own database, not the central one', function () {
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Tenant Site';
        $settings->save();

        $stored = DB::connection('tenant')->table('settings')->where('group', 'general')->where('name', 'site_name')->value('payload');

        expect(json_decode((string) $stored))->toBe('Tenant Site')
            ->and(Schema::connection(config('tenancy.database.central_connection'))->hasTable('settings'))->toBeFalse();
    });

    test('loaded settings are forgotten when the tenant changes, so the next tenant\'s are read', function () {
        $before = app(GeneralSettings::class);

        event(new TenancyBootstrapped(tenancy()));

        expect(app(GeneralSettings::class))->not->toBe($before);
    });
});

function siteSettingsMenuIsVisible(mixed $user): bool
{
    $menu = app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user);

    return str_contains(
        json_encode($menu->toArray(), JSON_UNESCAPED_SLASHES),
        route('admin.site-settings.general.edit', [], false)
    );
}
