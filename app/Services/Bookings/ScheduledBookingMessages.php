<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\MessageEvent;
use App\Models\Bookings\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sends the reminders, follow-ups and review requests that are due. Each booking gets each one at
 * most once, as any logged message about it counts, even one that couldn't be sent. Safe to run as
 * often as wanted.
 */
class ScheduledBookingMessages
{
    /** Follow-ups and review requests aren't sent for bookings that ended longer ago than this */
    protected const int LATEST_DAYS = 7;

    public function __construct(
        protected BookingMessages $messages,
        protected BookingMessenger $messenger,
    ) {}

    /**
     * @return int How many were sent
     */
    public function sendDue(): int
    {
        $sent = 0;

        foreach ([MessageEvent::Reminder, MessageEvent::FollowUp, MessageEvent::ReviewRequest] as $event) {
            $wording = $this->messages->wordingFor($event);

            if (! $wording['is_enabled'] || $wording['offset_hours'] === null) {
                continue;
            }

            foreach ($this->due($event, $wording['offset_hours']) as $booking) {
                $this->messenger->send($booking, $event);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Reminders go a number of hours before the start, for confirmed bookings, unless it was booked
     * inside that time, as the confirmation has only just gone. Follow-ups and review requests go a
     * number of hours after a completed booking ends.
     *
     * @return Collection<int, Booking>
     */
    protected function due(MessageEvent $event, int $hours): Collection
    {
        $query = Booking::query()
            ->with(['contact', 'location'])
            ->whereDoesntHave('messages', fn (Builder $query) => $query->where('event', $event->value));

        if ($event === MessageEvent::Reminder) {
            return $query
                ->where('status', BookingStatus::Confirmed)
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours($hours))
                ->get()
                ->filter(fn (Booking $booking) => $booking->starts_at !== null
                    && ($booking->created_at === null || $booking->created_at->lessThanOrEqualTo($booking->starts_at->copy()->subHours($hours))))
                ->values();
        }

        return $query
            ->where('status', BookingStatus::Completed)
            ->where('ends_at', '<=', now()->subHours($hours))
            ->where('ends_at', '>=', now()->subHours($hours)->subDays(self::LATEST_DAYS))
            ->get();
    }
}
