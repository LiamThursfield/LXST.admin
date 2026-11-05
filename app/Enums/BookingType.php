<?php

namespace App\Enums;

use App\Enums\Bookings\ResourceType;
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

    public const string DETAIL_RESOURCES = 'resources';

    /**
     * Which details of a service apply to this type
     *
     * @return list<string>
     */
    public function details(): array
    {
        return match ($this) {
            self::Appointment => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_RESOURCES],
            self::GroupClass, self::Ticket => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_CAPACITY],
            self::Table => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_PARTY_SIZE, self::DETAIL_RESOURCES],
            self::Hire => [self::DETAIL_BUFFERS, self::DETAIL_HIRE, self::DETAIL_RESOURCES],
            self::Visit => [self::DETAIL_DURATION, self::DETAIL_BUFFERS, self::DETAIL_TRAVEL, self::DETAIL_RESOURCES],
            self::Enquiry => [],
        };
    }

    /**
     * The kinds of resource a booking takes, unless the service says otherwise
     *
     * @return list<ResourceType>
     */
    public function defaultResourceTypes(): array
    {
        return match ($this) {
            self::Appointment, self::Visit => [ResourceType::Person],
            self::Table, self::Hire => [ResourceType::Space],
            self::GroupClass, self::Ticket, self::Enquiry => [],
        };
    }

    /**
     * Whether bookings take places on a scheduled session, rather than being given a time of their own
     */
    public function usesSessions(): bool
    {
        return $this === self::GroupClass || $this === self::Ticket;
    }

    /**
     * Whether the price is per place or hire unit, rather than for the whole booking
     */
    public function pricesPerUnit(): bool
    {
        return in_array($this, [self::GroupClass, self::Ticket, self::Hire], true);
    }

    public function uses(string $detail): bool
    {
        return in_array($detail, $this->details(), true);
    }
}
