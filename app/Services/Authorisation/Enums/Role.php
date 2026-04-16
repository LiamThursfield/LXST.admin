<?php

namespace App\Services\Authorisation\Enums;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
