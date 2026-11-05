<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Services\Industry\Terminology;
use Carbon\CarbonInterface;

/**
 * A service's rules for when and how often it can be booked, and when cancelling is late
 */
class BookingRules
{
    public function __construct(protected Terminology $terminology) {}

    /**
     * Whether a start time is within the notice needed and how far ahead it can be booked
     */
    public function allowsStart(Service $service, CarbonInterface $start): bool
    {
        return $this->startProblem($service, $start) === null;
    }

    /**
     * What breaks the rules, by the field to show it on. A walk-in is booked in person to start now,
     * so notice and how far ahead don't apply to it.
     *
     * @return array<string, string>
     */
    public function problems(Service $service, Booking $booking): array
    {
        $problems = [];

        if ($booking->starts_at !== null && ! $booking->is_walk_in) {
            $startProblem = $this->startProblem($service, $booking->starts_at);

            if ($startProblem !== null) {
                $problems[$booking->session_id === null ? 'time' : 'session_id'] = $startProblem;
            }
        }

        if ($service->max_per_customer !== null && filled($booking->customer_email) && $this->upcomingFor($service, (string) $booking->customer_email) >= $service->max_per_customer) {
            $problems['customer_email'] = $this->terminology->translate('bookings.rules.validation.max_per_customer', ['count' => $service->max_per_customer]);
        }

        return $problems;
    }

    /**
     * Whether cancelling now is inside the service's cancellation window
     */
    public function isLateToCancel(Booking $booking, ?CarbonInterface $at = null): bool
    {
        $hours = $booking->service?->cancellation_hours;

        return $hours !== null
            && $booking->starts_at !== null
            && ($at ?? now())->greaterThan($booking->starts_at->copy()->subHours($hours));
    }

    protected function startProblem(Service $service, CarbonInterface $start): ?string
    {
        if ($service->min_notice_minutes !== null && $start->lessThan(now()->addMinutes($service->min_notice_minutes))) {
            return $this->terminology->translate('bookings.rules.validation.min_notice', ['notice' => $this->duration($service->min_notice_minutes)]);
        }

        if ($service->max_advance_days !== null && $start->greaterThan(now()->addDays($service->max_advance_days)->endOfDay())) {
            return $this->terminology->translate('bookings.rules.validation.max_advance', ['days' => $service->max_advance_days]);
        }

        return null;
    }

    /**
     * The customer's bookings of the service still to come, or still waiting for a time
     */
    protected function upcomingFor(Service $service, string $email): int
    {
        return Booking::query()
            ->where('service_id', $service->id)
            ->where('customer_email', $email)
            ->whereIn('status', [BookingStatus::Requested, BookingStatus::Confirmed])
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '>', now()))
            ->count();
    }

    protected function duration(int $minutes): string
    {
        return $minutes % 1440 === 0
            ? trans_choice('bookings.rules.days', intdiv($minutes, 1440), ['count' => intdiv($minutes, 1440)])
            : ($minutes % 60 === 0
                ? trans_choice('bookings.rules.hours', intdiv($minutes, 60), ['count' => intdiv($minutes, 60)])
                : trans_choice('bookings.rules.minutes', $minutes, ['count' => $minutes]));
    }
}
