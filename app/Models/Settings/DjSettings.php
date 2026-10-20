<?php

namespace App\Models\Settings;

use App\Enums\Dj\RequestsMode;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsCasts\EnumCast;

/**
 * The DJ module's settings: gig defaults, and the request settings each gig uses unless it
 * overrides them (see GigRequestSettings)
 */
class DjSettings extends Settings
{
    /** The timezone new gigs start in */
    public string $default_timezone;

    /**
     * Where visitors make requests, e.g. https://mydjsite.com/request. {gig} is replaced with the
     * gig's public ID for a link to one gig; without it, the link always means the current gig.
     */
    public ?string $request_page_url;

    /** Whether the hosted request page on the tenant's domain is on; links use it when request_page_url is blank */
    public bool $hosted_page_enabled;

    public RequestsMode $requests_mode;

    /** How long before the start requests open, when scheduled */
    public int $requests_open_minutes_before;

    /** 0 means no limit */
    public int $max_requests_per_visitor;

    /** 0 means no limit */
    public int $max_pending_requests;

    public bool $allow_custom_requests;

    public bool $show_public_list;

    public bool $allow_upvotes;

    /** Whether the public request list shows the guest pass that made each request */
    public bool $show_pass_labels;

    /** Whether the gig's tracklist (now playing and recently played) is public */
    public bool $show_tracklist;

    /** How many tracks a page of the public tracklist shows */
    public int $tracklist_per_page;

    /** How long a track is held back from the public tracklist, e.g. to keep the next track a surprise */
    public int $tracklist_delay_minutes;

    public static function group(): string
    {
        return 'dj';
    }

    /**
     * @return array<string, string>
     */
    public static function casts(): array
    {
        return [
            'requests_mode' => EnumCast::class.':'.RequestsMode::class,
        ];
    }
}
