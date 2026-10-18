<?php

use App\Enums\Dj\RequestsMode;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('dj.default_timezone', (string) config('app.timezone'));
        $this->migrator->add('dj.request_page_url', null);
        $this->migrator->add('dj.requests_mode', RequestsMode::Scheduled->value);
        $this->migrator->add('dj.requests_open_minutes_before', 60);
        $this->migrator->add('dj.max_requests_per_visitor', 3);
        $this->migrator->add('dj.max_pending_requests', 0);
        $this->migrator->add('dj.allow_custom_requests', true);
        $this->migrator->add('dj.show_public_list', true);
        $this->migrator->add('dj.allow_upvotes', true);
    }

    public function down(): void
    {
        foreach ([
            'default_timezone', 'request_page_url', 'requests_mode', 'requests_open_minutes_before',
            'max_requests_per_visitor', 'max_pending_requests', 'allow_custom_requests', 'show_public_list', 'allow_upvotes',
        ] as $name) {
            $this->migrator->delete("dj.{$name}");
        }
    }
};
