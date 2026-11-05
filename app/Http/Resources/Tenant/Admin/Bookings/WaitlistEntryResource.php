<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Enums\Bookings\WaitlistStatus;
use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\WaitlistEntry;
use App\Services\Bookings\BookingAvailability;
use App\Services\Industry\Terminology;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var WaitlistEntry $entry */
        $entry = $this->resource;

        if ($entry->status !== WaitlistStatus::Waiting || ! ($request->user()?->can('update', $entry) ?? false)) {
            return $entry->booking_id === null ? [] : [
                Action::make(app(Terminology::class)->translate('bookings.waitlist.actions.view_booking'), route('admin.bookings.bookings.show', $entry->booking_id))->icon('i-lucide-eye'),
            ];
        }

        return [
            Action::make(__('bookings.waitlist.actions.book'), route('admin.bookings.waitlist.book', $entry))
                ->icon('i-lucide-calendar-plus')
                ->method('POST'),
            Action::make(__('bookings.waitlist.actions.remove'), route('admin.bookings.waitlist.destroy', $entry))
                ->icon('i-lucide-x')
                ->method('DELETE')
                ->requireConfirmation(__('bookings.waitlist.confirm_remove', ['name' => $entry->customer_name])),
        ];
    }

    /**
     * Transform the resource into an array. A place shows as free when a timetable entry has room
     * for them now; for a date, the booking form shows what's free.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WaitlistEntry $entry */
        $entry = $this->resource;
        $session = $entry->session;
        $availability = app(BookingAvailability::class);

        return $this->appendActions($request, [
            'id' => $entry->id,
            'customer_name' => $entry->customer_name,
            'customer_email' => $entry->customer_email,
            'service_name' => $entry->service?->name,
            'wanted' => $session !== null
                ? $session->starts_at->copy()->setTimezone($availability->timezoneFor($session->location))->format('D j M Y, H:i')
                : $entry->wanted_on?->format('D j M Y'),
            'quantity' => $entry->quantity,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'place_free' => $session !== null && $entry->status === WaitlistStatus::Waiting && $availability->placesLeft($session) >= $entry->quantity,
            'notes' => $entry->notes,
        ]);
    }
}
