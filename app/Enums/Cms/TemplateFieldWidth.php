<?php

namespace App\Enums\Cms;

use App\Enums\Traits\HasLabels;

/**
 * How much of the content editor's width a template field takes. Half-width fields sit side by
 * side on wide screens and stack on narrow ones.
 */
enum TemplateFieldWidth: string
{
    use HasLabels;

    case Full = 'full';
    case Half = 'half';
}
