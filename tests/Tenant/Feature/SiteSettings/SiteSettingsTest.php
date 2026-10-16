<?php

use App\Models\FileManager\File;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Models\Settings\ThemeSettings;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\FileManager\FileManagerStorage;
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

describe('theme settings', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
    });

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function themeData(array $overrides = []): array
    {
        return [
            'logo_file_id' => null,
            'favicon_file_id' => null,
            'primary_color' => 'amber',
            'neutral_color' => 'zinc',
            'site_colors' => ThemeSettings::DEFAULT_SITE_COLORS,
            ...$overrides,
        ];
    }

    test('the theme starts with the admin\'s built-in palettes and legacy\'s website colours', function () {
        $this->get(route('admin.site-settings.theme.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/site-settings/Theme')
                ->where('settings.logo_file_id', null)
                ->where('settings.primary_color', 'amber')
                ->where('settings.neutral_color', 'zinc')
                ->where('settings.site_colors', ThemeSettings::DEFAULT_SITE_COLORS)
            );
    });

    test('the logo, favicon and colours can be changed, and are shared with every admin page', function () {
        $logo = File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png', 'mime_type' => 'image/png']);
        $favicon = File::factory()->create(['name' => 'icon.svg', 'path' => 'icon.svg', 'mime_type' => 'image/svg+xml']);

        $this->put(route('admin.site-settings.theme.update'), themeData([
            'logo_file_id' => $logo->id,
            'favicon_file_id' => $favicon->id,
            'primary_color' => 'blue',
            'neutral_color' => 'slate',
            'site_colors' => ['primary' => '#ff0000', 'accent' => ''],
        ]))
            ->assertRedirect(route('admin.site-settings.theme.edit'))
            ->assertSessionHas('success', __('site_settings.theme.updated'));

        $theme = app(ThemeSettings::class);
        $storage = app(FileManagerStorage::class);

        expect($theme->logo_file_id)->toBe($logo->id)
            ->and($theme->primary_color)->toBe('blue')
            // Upper-cased, and colours that were left empty or not sent are left to the website
            ->and($theme->site_colors['primary'])->toBe('#FF0000')
            ->and($theme->site_colors['accent'])->toBeNull()
            ->and($theme->site_colors['base'])->toBeNull();

        $this->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.name', tenant('name'))
                ->where('site.logoUrl', $storage->url($logo))
                ->where('site.faviconUrl', $storage->url($favicon))
                ->where('site.colors', ['primary' => 'blue', 'neutral' => 'slate'])
            );
    });

    test('the logo and favicon must be images from the File Manager', function () {
        $pdf = File::factory()->create(['name' => 'prices.pdf', 'path' => 'prices.pdf', 'mime_type' => 'application/pdf']);

        $this->put(route('admin.site-settings.theme.update'), themeData(['logo_file_id' => $pdf->id, 'favicon_file_id' => 999]))
            ->assertSessionHasErrors([
                'logo_file_id' => __('site_settings.theme.validation.image'),
                'favicon_file_id' => __('site_settings.theme.validation.image'),
            ]);
    });

    test('colours must be one of the palettes, or hex colours for the website', function () {
        $this->put(route('admin.site-settings.theme.update'), themeData([
            'primary_color' => 'slate',
            'neutral_color' => 'amber',
            'site_colors' => ['primary' => 'blue', 'unknown' => '#FFFFFF'],
        ]))->assertSessionHasErrors([
            'primary_color',
            'neutral_color',
            'site_colors.primary' => __('site_settings.theme.validation.hex'),
            'site_colors',
        ]);
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
        $this->put(route('admin.site-settings.theme.update'), ['primary_color' => 'blue'])->assertForbidden();
    });

    test('users without settings permissions cannot see settings, or the menu item', function () {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user);

        $this->get(route('admin.site-settings.general.edit'))->assertForbidden();
        $this->get(route('admin.site-settings.integrations.edit'))->assertForbidden();
        $this->get(route('admin.site-settings.theme.edit'))->assertForbidden();

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

    test('loaded settings are forgotten when the tenant changes, so the next tenant\'s are read', function (string $settingsClass) {
        $before = app($settingsClass);

        expect(app($settingsClass))->toBe($before);

        event(new TenancyBootstrapped(tenancy()));

        expect(app($settingsClass))->not->toBe($before);
    })->with([GeneralSettings::class, IntegrationSettings::class, ThemeSettings::class]);
});

function siteSettingsMenuIsVisible(mixed $user): bool
{
    $menu = app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user);

    return str_contains(
        json_encode($menu->toArray(), JSON_UNESCAPED_SLASHES),
        route('admin.site-settings.general.edit', [], false)
    );
}
