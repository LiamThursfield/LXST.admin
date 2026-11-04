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

    public const string DETAIL_DURATION = 'duration';

    public const string DETAIL_BUFFERS = 'buffers';

    public const string DETAIL_CAPACITY = 'capacity';

    public const string DETAIL_PARTY_SIZE = 'party_size';

    public const string DETAIL_HIRE = 'hire';

    public const string DETAIL_TRAVEL = 'travel';

    /**
     * Which details of a service apply to this type
     *
     * @return list<string>
     */
    public function details(): array
    {
        return match ($this) {
            self::Appointment => [self::DETAIL_DURATION, self::DETAIL_BUFFERS],
            self::GroupClass, self::Ticket => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_CAPACITY],
            self::Table => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_PARTY_SIZE],
            self::Hire => [self::DETAIL_BUFFERS, self::DETAIL_HIRE],
            self::Visit => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_TRAVEL],
            self::Enquiry => [],
        };
    }

    public function uses(string $detail): bool
    {
        return in_array($detail, $this->details(), true);
    }
}
