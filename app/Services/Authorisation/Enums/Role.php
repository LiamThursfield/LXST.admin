<?php

namespace App\Services\Authorisation\Enums;

use App\Enums\Traits\HasLabels;

enum Role: string
{
    use HasLabels;

    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
