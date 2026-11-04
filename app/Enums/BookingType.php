<?php

namespace App\Enums;

use App\Enums\Traits\HasLabels;

/**
 * The ways any business's services can be booked (#176). One booking engine handles them all, so
 * these describe how a booking works rather than which industry it's for.
 */
enum BookingType: string
{
    use HasLabels;

    case Appointment = 'appointment';
    case GroupClass = 'class';
    case Table = 'table';
    case Hire = 'hire';
    case Visit = 'visit';
    case Ticket = 'ticket';
    case Enquiry = 'enquiry';
}
