<?php

namespace App\Enums\Api;

use App\Enums\Traits\HasLabels;

/**
 * What a tenant API token may do. Routes check one with the `auth.tenant-token:{ability}` middleware
 */
enum ApiTokenAbility: string
{
    use HasLabels;

    /** Syncing the DJ library, e.g. from the Rekordbox CLI tool */
    case DjSync = 'dj:sync';

    /** Pushing the DJ's now-playing tracks */
    case DjNowPlaying = 'dj:now-playing';
}
