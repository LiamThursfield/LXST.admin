<?php

namespace App\Services\Authorisation\Enums;

enum TrackerPermission: string
{
    case ViewTasks = 'tracker.view-tasks';
    case ManageTasks = 'tracker.manage-tasks';
    case LogEntries = 'tracker.log-entries';

    case ViewCategories = 'tracker.view-categories';
    case ManageCategories = 'tracker.manage-categories';
    case ManagePublicCategories = 'tracker.manage-public-categories';

    case ManageHolidays = 'tracker.manage-holidays';

    /**
     * Returns the cases that refer to a user viewing/managing
     * their own trackables
     *
     * @return TrackerPermission[]
     */
    public static function personalCases(): array
    {
        return [
            TrackerPermission::ViewTasks,
            TrackerPermission::ManageTasks,
            TrackerPermission::LogEntries,
            TrackerPermission::ViewCategories,
            TrackerPermission::ManageCategories,
            TrackerPermission::ManageHolidays,
        ];
    }
}
