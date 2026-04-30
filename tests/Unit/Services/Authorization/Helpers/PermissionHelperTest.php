<?php

namespace Tests\Unit\Services\Authorization\Helpers;

use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Helpers\PermissionHelper;

describe('middlewareForPermissions', function () {
    it('will return the expected string for a single permission', function () {
        $permission = CorePermission::ManagePermissions;

        expect(PermissionHelper::middlewareForPermissions($permission))
            ->toBe('permission:'.$permission->value);
    });

    it('will return the expected string for a multiple permissions', function () {
        $permissionOne = CorePermission::ManagePermissions;
        $permissionTwo = CorePermission::ViewUsers;

        expect(PermissionHelper::middlewareForPermissions([$permissionOne, $permissionTwo]))
            ->toBe("permission:$permissionOne->value|$permissionTwo->value");
    });
});
