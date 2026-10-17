<?php

namespace App\Services\Central;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a central admin user. Central users have no roles (they're all super users), and the
 * central database has no permission tables, so the row is deleted directly: deleting the model
 * would run spatie/laravel-permission's role clean up, which needs those tables.
 */
class CentralUserDeleter
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            User::query()->whereKey($user->getKey())->delete();
        });
    }

    /**
     * Whether this is the last central user, who can't be deleted (no one could log in)
     */
    public function isLastUser(User $user): bool
    {
        return User::query()->whereKeyNot($user->getKey())->doesntExist();
    }
}
