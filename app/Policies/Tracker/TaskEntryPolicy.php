<?php

namespace App\Policies\Tracker;

use App\Models\Tracker\TaskEntry;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;

class TaskEntryPolicy
{
    /**
     * Whether the user can delete the given entry. Entries are private to
     * their owner.
     */
    public function delete(User $user, TaskEntry $entry): bool
    {
        return $entry->user_id === $user->id
            && $user->can(TrackerPermission::LogEntries);
    }
}
