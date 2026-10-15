<?php

namespace App\Policies\Tracker;

use App\Models\Tracker\Holiday;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;

class HolidayPolicy
{
    /**
     * Whether the user can list holidays.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TrackerPermission::ManageHolidays);
    }

    /**
     * Whether the user can view the given holiday. Holidays are private to their owner.
     */
    public function view(User $user, Holiday $holiday): bool
    {
        return $user->can(TrackerPermission::ManageHolidays)
            && $holiday->user_id === $user->id;
    }

    /**
     * Whether the user can create holidays.
     */
    public function create(User $user): bool
    {
        return $user->can(TrackerPermission::ManageHolidays);
    }

    /**
     * Whether the user can update the given holiday.
     */
    public function update(User $user, Holiday $holiday): bool
    {
        return $holiday->user_id === $user->id
            && $user->can(TrackerPermission::ManageHolidays);
    }

    /**
     * Whether the user can delete the given holiday.
     */
    public function delete(User $user, Holiday $holiday): bool
    {
        return $this->update($user, $holiday);
    }
}
