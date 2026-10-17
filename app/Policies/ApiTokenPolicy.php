<?php

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\Authorisation\Enums\SettingsPermission;

/**
 * Tokens belong to the site, so anyone who can manage them sees and revokes every token,
 * whoever created it
 */
class ApiTokenPolicy
{
    /**
     * Whether the user can list the site's API tokens.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(SettingsPermission::ManageApiTokens);
    }

    /**
     * Whether the user can create API tokens.
     */
    public function create(User $user): bool
    {
        return $user->can(SettingsPermission::ManageApiTokens);
    }

    /**
     * Whether the user can revoke the given API token.
     */
    public function delete(User $user, ApiToken $apiToken): bool
    {
        return $user->can(SettingsPermission::ManageApiTokens);
    }
}
