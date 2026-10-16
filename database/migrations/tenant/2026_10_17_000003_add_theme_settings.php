<?php

use App\Models\Settings\ThemeSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.logo_file_id', null);
        $this->migrator->add('theme.favicon_file_id', null);
        // The admin's built-in look (vite.tenant.config.ts)
        $this->migrator->add('theme.primary_color', 'amber');
        $this->migrator->add('theme.neutral_color', 'zinc');
        $this->migrator->add('theme.site_colors', ThemeSettings::DEFAULT_SITE_COLORS);
    }

    public function down(): void
    {
        $this->migrator->delete('theme.logo_file_id');
        $this->migrator->delete('theme.favicon_file_id');
        $this->migrator->delete('theme.primary_color');
        $this->migrator->delete('theme.neutral_color');
        $this->migrator->delete('theme.site_colors');
    }
};
