<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\BookingOption;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return $request->user()?->can('view', $booking)
            ? [Action::make(__('common.actions.view'), route('admin.bookings.bookings.show', $booking))->icon('i-lucide-eye')]
            : [];
    }

    /**
     * Transform the resource into an array. Times are written out in the booking's own timezone.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return $this->appendActions($request, [
            'id' => $booking->id,
            'when' => $booking->starts_at?->setTimezone($booking->timezone)->format('D j M Y, H:i'),
            'ends' => $booking->ends_at?->setTimezone($booking->timezone)->format('D j M Y, H:i'),
            'timezone' => $booking->timezone,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'needs_time' => $booking->needsTime(),
            'next_statuses' => array_map(fn (BookingStatus $status) => $status->value, $booking->status->nextStatuses()),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'contact_id' => $booking->contact_id,
            'service_name' => $booking->service_name,
            'location_name' => $this->whenLoaded('location', fn () => $booking->location?->name),
            'resource_names' => $this->whenLoaded('resources', fn () => $booking->resources->map(fn (BookableResource $resource) => $resource->name)->values()),
            'quantity' => $booking->quantity,
            'price_type' => $booking->price_type->value,
            'price' => $booking->price === null ? null : number_format($booking->price / 100, 2, '.', ''),
            'notes' => $booking->notes,
            'options' => $this->whenLoaded('options', fn () => $booking->options->map(fn (BookingOption $option) => $option->name)->values()),
        ]);
    }
}
