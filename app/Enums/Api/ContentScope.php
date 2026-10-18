<?php

namespace App\Enums\Api;

/**
 * A headless API whose cached responses share a content version, refreshed together when the
 * admin changes what they show (see ContentVersion and the `content.changed:{scope}` middleware)
 */
enum ContentScope: string
{
    /** Pages, menus, forms and site settings */
    case Cms = 'cms';

    /** Gigs, request context and library search */
    case Dj = 'dj';
}
