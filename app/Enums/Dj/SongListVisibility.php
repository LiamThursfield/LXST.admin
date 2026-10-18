<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

enum SongListVisibility: string
{
    use HasLabels;

    /** Only in the admin, or to guest passes (#147) */
    case Private = 'private';

    /** Also on the request page */
    case Public = 'public';
}
