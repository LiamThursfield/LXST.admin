<?php

namespace App\Services\Navigation\Enums;

/**
 * This enum defines the possible values for the MenuItem->type property
 *
 * @see https://ui.nuxt.com/docs/components/navigation-menu
 */
enum MenuItemType: string
{
    case LABEL = 'label';
    case LINK = 'link';
    case TRIGGER = 'trigger';
}
