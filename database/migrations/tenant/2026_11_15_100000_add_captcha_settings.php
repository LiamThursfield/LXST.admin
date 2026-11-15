<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // No captcha until the tenant sets one up (#74); song requests don't ask for one by default
        $this->migrator->add('integrations.captcha_provider', null);
        $this->migrator->add('integrations.captcha_site_key', null);
        $this->migrator->addEncrypted('integrations.captcha_secret_key', null);
        $this->migrator->add('dj.require_captcha', false);
    }

    public function down(): void
    {
        foreach (['integrations.captcha_provider', 'integrations.captcha_site_key', 'integrations.captcha_secret_key', 'dj.require_captcha'] as $name) {
            $this->migrator->delete($name);
        }
    }
};
