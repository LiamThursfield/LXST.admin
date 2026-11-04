<?php

namespace App\Services\Authorisation\Enums;

enum BookingsPermission: string
{
    case ViewServices = 'bookings.view-services';
    case ManageServices = 'bookings.manage-services';
}
