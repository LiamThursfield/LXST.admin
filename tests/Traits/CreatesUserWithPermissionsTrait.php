<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;

/**
 * This Trait was taken/adapted from previous work by https://github.com/sdsmith1981
 * It assists with setting up a test tenant, ensuring parallel tests work as expected, and improving test performance
 */
trait CreatesUserWithPermissionsTrait
{
    /**
     * @param  array<string>  $permissions
     * @param  array<mixed>  $userAttributes
     */
    protected function createUserWithPermissions(
        array $permissions,
        array $userAttributes = []
    ): User {
        $user = User::factory()->create($userAttributes);
        $user->givePermissionTo($permissions);

        return $user;
    }
}
