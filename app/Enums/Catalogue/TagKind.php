<?php

namespace App\Enums\Catalogue;

use App\Enums\Traits\HasLabels;

/**
 * What a catalogue tag says about an item: what's in it, who it suits, or anything else
 */
enum TagKind: string
{
    use HasLabels;

    case Allergen = 'allergen';
    case Dietary = 'dietary';
    case Other = 'other';
}
