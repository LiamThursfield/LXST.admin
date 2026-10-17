<?php

use App\Enums\TenantModule;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\FileManager\File;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Models\Settings\ThemeSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\FileManager\FileManagerStorage;

/**
 * A menu linking to a published page and an unpublished one
 */
function siteMenu(string $slug): Menu
{
    $published = Page::factory()->create(['slug' => 'team']);
    $draft = Page::factory()->create(['slug' => 'draft']);
    $draft->url->update(['is_enabled' => false]);

    return Menu::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'menu_items' => [
        ['label' => 'Team', 'link_type' => 'page', 'page_id' => $published->id, 'children' => []],
        ['label' => 'Draft', 'link_type' => 'page', 'page_id' => $draft->id, 'children' => []],
    ]]);
}

describe('site', function () {
    test('the site comes back with its name, branding, theme, GTM ID and menus', function () {
        $logo = File::factory()->create();
        $general = app(GeneralSettings::class);
        $general->site_name = 'Acme';
        $general->save();
        $theme = app(ThemeSettings::class);
        $theme->logo_file_id = $logo->id;
        $theme->primary_color = 'emerald';
        $theme->site_colors = ['primary' => '#123456'];
        $theme->save();
        $integrations = app(IntegrationSettings::class);
        $integrations->gtm_id = 'GTM-ABC1234';
        $integrations->save();
        siteMenu('main');

        $this->getJson(route('api.v1.site'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme')
            ->assertJsonPath('data.logo_url', app(FileManagerStorage::class)->url($logo))
            ->assertJsonPath('data.favicon_url', null)
            ->assertJsonPath('data.theme.primary', 'emerald')
            ->assertJsonPath('data.theme.colors.primary', '#123456')
            ->assertJsonPath('data.theme.colors.accent', null)
            ->assertJsonCount(count(ThemeSettings::DEFAULT_SITE_COLORS), 'data.theme.colors')
            ->assertJsonPath('data.gtm_id', 'GTM-ABC1234')
            ->assertJsonPath('data.menus.main.name', 'Main')
            ->assertJsonCount(1, 'data.menus.main.items')
            ->assertJsonPath('data.menus.main.items.0.url', '/team');
    });

    test('menus are left out without the CMS module', function () {
        siteMenu('main');
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->getJson(route('api.v1.site'))
            ->assertOk()
            ->assertJsonPath('data.menus', []);
    });

    test('a settings change refreshes the cached site straight away', function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));

        $this->getJson(route('api.v1.site'))->assertJsonPath('data.gtm_id', null);

        $this->put(route('admin.site-settings.integrations.update'), ['gtm_id' => 'GTM-ABC1234'])
            ->assertSessionHasNoErrors();

        $this->getJson(route('api.v1.site'))->assertJsonPath('data.gtm_id', 'GTM-ABC1234');
    });
});

describe('menus', function () {
    test('a menu comes back by its slug, leaving out unpublished pages', function () {
        siteMenu('footer');

        $this->getJson(route('api.v1.cms.menus.show', 'footer'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'footer')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.label', 'Team');
    });

    test('an unknown menu isn\'t found', function () {
        $this->getJson(route('api.v1.cms.menus.show', 'nope'))->assertNotFound();
    });

    test('the menu API needs the CMS module', function () {
        siteMenu('footer');
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->getJson(route('api.v1.cms.menus.show', 'footer'))->assertNotFound();
    });
});
