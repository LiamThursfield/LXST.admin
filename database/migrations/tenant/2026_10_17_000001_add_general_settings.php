<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Start with the tenant's name, so the site has a sensible name before anyone edits it
        $this->migrator->add('general.site_name', tenant('name') ?? config('app.name'));
    }

    public function down(): void
    {
        $this->migrator->delete('general.site_name');
    }
};
