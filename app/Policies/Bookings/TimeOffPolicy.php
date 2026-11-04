<?php

namespace App\Policies\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Models\Bookings\TimeOff;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class TimeOffPolicy
{
    /**
     * Whether the user can see a list of time off: everyone's, or just their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewTimeOff)
            || $user->can(BookingsPermission::ManageTimeOff)
            || $user->can(BookingsPermission::RequestTimeOff);
    }

    /**
     * Whether the user can see everyone's time off, which can say why people are away.
     */
    public function viewEveryone(User $user): bool
    {
        return $user->can(BookingsPermission::ViewTimeOff) || $user->can(BookingsPermission::ManageTimeOff);
    }

    /**
     * Whether the user can ask for time off, or record it for others.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::RequestTimeOff) || $user->can(BookingsPermission::ManageTimeOff);
    }

    /**
     * Whether the user can record time off for any resource, without it needing approval.
     */
    public function manage(User $user): bool
    {
        return $user->can(BookingsPermission::ManageTimeOff);
    }

    /**
     * Whether the user can approve or decline the given request.
     */
    public function review(User $user, TimeOff $timeOff): bool
    {
        return $timeOff->status === TimeOffStatus::Requested && $user->can(BookingsPermission::ManageTimeOff);
    }

    /**
     * Whether the user can remove the given time off: any of it if they manage time off, or their
     * own request while it's still waiting.
     */
    public function delete(User $user, TimeOff $timeOff): bool
    {
        if ($user->can(BookingsPermission::ManageTimeOff)) {
            return true;
        }

        return $timeOff->status === TimeOffStatus::Requested
            && $timeOff->requested_by_id === $user->id
            && $user->can(BookingsPermission::RequestTimeOff);
    }
}
