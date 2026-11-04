<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

/**
 * People, listed under the tenant's word for them, e.g. coaches or stylists. They're resources like
 * any other, so everything but the list they're shown in is shared.
 */
class StaffController extends ResourceController
{
    protected bool $forStaff = true;
}
