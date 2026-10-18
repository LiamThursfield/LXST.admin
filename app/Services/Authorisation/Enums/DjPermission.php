<?php

namespace App\Services\Authorisation\Enums;

/**
 * The tenant is the DJ, so these cover the site's own library, gigs, song requests and stats
 */
enum DjPermission: string
{
    case ViewLibrary = 'dj.view-library';
    case ManageLibrary = 'dj.manage-library';

    case ViewGigs = 'dj.view-gigs';
    case ManageGigs = 'dj.manage-gigs';

    /** Adding to and editing setlists; viewing them needs dj.view-gigs */
    case ManageSetlists = 'dj.manage-setlists';

    /** Working through the song request queue */
    case ManageRequests = 'dj.manage-requests';

    /** Gig stats and The Annual Drop */
    case ViewStats = 'dj.view-stats';
}
