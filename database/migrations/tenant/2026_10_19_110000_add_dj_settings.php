<?php

use App\Enums\Dj\RequestsMode;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('dj.default_timezone', (string) config('app.timezone'));
        $this->migrator->add('dj.request_page_url', null);
        // The request page on the tenant's own domain (#123), for DJs without their own site
        $this->migrator->add('dj.hosted_page_enabled', true);
        $this->migrator->add('dj.requests_mode', RequestsMode::Scheduled->value);
        $this->migrator->add('dj.requests_open_minutes_before', 60);
        $this->migrator->add('dj.max_requests_per_visitor', 3);
        $this->migrator->add('dj.max_pending_requests', 0);
        $this->migrator->add('dj.allow_custom_requests', true);
        $this->migrator->add('dj.show_public_list', true);
        $this->migrator->add('dj.allow_upvotes', true);
        // Off by default: guests' pass labels (e.g. "Bride") stay off the public request list
        $this->migrator->add('dj.show_pass_labels', false);
    }

    public function down(): void
    {
        foreach ([
            'default_timezone', 'request_page_url', 'hosted_page_enabled', 'requests_mode', 'requests_open_minutes_before',
            'max_requests_per_visitor', 'max_pending_requests', 'allow_custom_requests', 'show_public_list', 'allow_upvotes',
            'show_pass_labels',
        ] as $name) {
            $this->migrator->delete("dj.{$name}");
        }
    }
};
