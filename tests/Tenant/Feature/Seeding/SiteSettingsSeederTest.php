<?php

use App\Models\FileManager\File;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Models\Settings\MailSettings;
use App\Models\Settings\ThemeSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Database\Seeders\FileManagerSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('file_manager');
    $this->seed([FileManagerSeeder::class, SiteSettingsSeeder::class]);
});

test('the site settings seeder sets the name, a theme with the seeded logo and favicon, a demo sender and a GTM ID', function () {
    $theme = app(ThemeSettings::class);

    expect(app(GeneralSettings::class)->site_name)->toBe(SiteSettingsSeeder::SITE_NAME)
        ->and($theme->logo_file_id)->toBe(File::query()->where('path', 'branding/logo.png')->value('id'))
        ->and($theme->favicon_file_id)->toBe(File::query()->where('path', 'branding/favicon.png')->value('id'))
        ->and($theme->primary_color)->toBeIn(ThemeSettings::PRIMARY_PALETTES)
        ->and($theme->neutral_color)->toBeIn(ThemeSettings::NEUTRAL_PALETTES)
        ->and(array_keys($theme->site_colors))->toBe(array_keys(ThemeSettings::DEFAULT_SITE_COLORS))
        // The app's own mailer, so mail can't fail on made-up credentials
        ->and(app(MailSettings::class)->driver)->toBeNull()
        ->and(app(MailSettings::class)->from_address)->toBe('hello@example.com')
        ->and(app(IntegrationSettings::class)->gtm_id)->not->toBeNull();
});

test('the site settings pages load with the seeded settings', function () {
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings, SettingsPermission::ManageSettings]));

    foreach (['general', 'theme', 'mail', 'integrations'] as $page) {
        $this->get(route("admin.site-settings.{$page}.edit"))->assertOk();
    }
});
