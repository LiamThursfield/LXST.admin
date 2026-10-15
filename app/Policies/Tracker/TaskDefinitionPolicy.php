<?php

namespace App\Policies\Tracker;

use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;

class TaskDefinitionPolicy
{
    /**
     * Whether the user can list task definitions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TrackerPermission::ViewTasks);
    }

    /**
     * Whether the user can view the given task definition. Definitions are
     * private to their owner.
     */
    public function view(User $user, TaskDefinition $taskDefinition): bool
    {
        return $user->can(TrackerPermission::ViewTasks)
            && $taskDefinition->user_id === $user->id;
    }

    /**
     * Whether the user can create task definitions.
     */
    public function create(User $user): bool
    {
        return $user->can(TrackerPermission::ManageTasks);
    }

    /**
     * Whether the user can update the given task definition.
     */
    public function update(User $user, TaskDefinition $taskDefinition): bool
    {
        return $taskDefinition->user_id === $user->id
            && $user->can(TrackerPermission::ManageTasks);
    }

    /**
     * Whether the user can log progress entries against the given task.
     */
    public function logEntry(User $user, TaskDefinition $taskDefinition): bool
    {
        return $taskDefinition->user_id === $user->id
            && $user->can(TrackerPermission::LogEntries);
    }

    /**
     * Whether the user can delete the given task definition.
     */
    public function delete(User $user, TaskDefinition $taskDefinition): bool
    {
        return $this->update($user, $taskDefinition);
    }
}
