<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\ServiceSession;
use App\Services\Bookings\BookingAvailability;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceSessionResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var ServiceSession $session */
        $session = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $session)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.bookings.sessions.edit', $session))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $session)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.bookings.sessions.destroy', $session))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('bookings.sessions.confirm_delete'));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array. Times are in the location's timezone, as the form takes them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServiceSession $session */
        $session = $this->resource;
        $availability = app(BookingAvailability::class);
        $start = $session->starts_at->copy()->setTimezone($availability->timezoneFor($session->location));

        return $this->appendActions($request, [
            'id' => $session->id,
            'service_id' => $session->service_id,
            'service_name' => $session->service?->name,
            'location_id' => $session->location_id,
            'location_name' => $session->location?->name,
            'when' => $start->format('D j M Y, H:i'),
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'duration_minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
            'capacity' => $session->capacity,
            'places_left' => $availability->placesLeft($session),
            'waiting' => $session->getAttribute('waitlist_entries_count'),
            'resource_ids' => $session->resources->map(fn (BookableResource $resource) => $resource->id)->values(),
            'resource_names' => $session->resources->map(fn (BookableResource $resource) => $resource->name)->join(', '),
        ]);
    }
}
