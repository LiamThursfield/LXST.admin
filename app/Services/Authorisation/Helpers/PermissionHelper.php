<?php

namespace App\Services\Authorisation\Helpers;

use BackedEnum;
use Illuminate\Support\Arr;

class PermissionHelper
{
    /**
     * Returns the middleware string to apply one or more
     * permission enum members
     * Example: 'permission:core.view-users|core.manage-users'
     *
     * @param  BackedEnum|array<BackedEnum>  $permissions
     */
    public static function middlewareForPermissions(BackedEnum|array $permissions): string
    {
        $permissionStrings = Arr::map(
            Arr::wrap($permissions),
            fn (BackedEnum $permission) => $permission->value
        );

        return 'permission:'.Arr::join($permissionStrings, '|');
    }
}
