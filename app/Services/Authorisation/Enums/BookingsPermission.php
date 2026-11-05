<?php

namespace App\Services\Authorisation\Enums;

enum BookingsPermission: string
{
    case ViewBookings = 'bookings.view-bookings';
    case ManageBookings = 'bookings.manage-bookings';

    case ViewServices = 'bookings.view-services';
    case ManageServices = 'bookings.manage-services';

    case ViewResources = 'bookings.view-resources';
    case ManageResources = 'bookings.manage-resources';

    case RequestTimeOff = 'bookings.request-time-off';
    case ViewTimeOff = 'bookings.view-time-off';
    case ManageTimeOff = 'bookings.manage-time-off';

    case ViewStaffReports = 'bookings.view-staff-reports';
}
