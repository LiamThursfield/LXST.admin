<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a booking message is about. Reminders, follow-ups and review requests are sent on a schedule;
 * a custom message is written by staff there and then.
 */
enum MessageEvent: string
{
    use HasLabels;

    case Booked = 'booked';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case Reminder = 'reminder';
    case FollowUp = 'follow_up';
    case ReviewRequest = 'review_request';
    case Custom = 'custom';

    /**
     * Whether it's sent on a schedule, a number of hours before or after the booking
     */
    public function isScheduled(): bool
    {
        return in_array($this, [self::Reminder, self::FollowUp, self::ReviewRequest], true);
    }

    /**
     * Whether it has wording the tenant can change. A custom message is written each time.
     */
    public function hasTemplate(): bool
    {
        return $this !== self::Custom;
    }

    /**
     * Follow-ups and review requests are off until a tenant chooses to send them
     */
    public function isEnabledByDefault(): bool
    {
        return ! in_array($this, [self::FollowUp, self::ReviewRequest], true);
    }

    /**
     * Hours before the start for a reminder, or after the end for the others
     */
    public function defaultOffsetHours(): ?int
    {
        return match ($this) {
            self::Reminder, self::ReviewRequest => 24,
            self::FollowUp => 2,
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function withTemplates(): array
    {
        return array_values(array_filter(self::cases(), fn (self $event) => $event->hasTemplate()));
    }
}
