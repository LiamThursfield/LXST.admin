<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Data\Bookings\BookingSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\BookingAvailabilityRequest;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingAvailability;
use App\Services\Bookings\BookingRules;
use Illuminate\Http\JsonResponse;

class BookingAvailabilityController extends Controller
{
    /**
     * The free times on a date, or the upcoming sessions with places, for the booking form
     */
    public function __invoke(BookingAvailabilityRequest $request, BookingAvailability $availability, BookingRules $rules): JsonResponse
    {
        $keepRules = ! $request->boolean('ignore_rules');
        $service = Service::query()->findOrFail($request->integer('service_id'));
        $location = $request->filled('location_id') ? OrganisationUnit::query()->find($request->integer('location_id')) : null;
        $quantity = max(1, $request->integer('quantity', 1));
        /** @var list<int|string> $optionIds */
        $optionIds = $request->validated('option_ids') ?? [];
        /** @var list<int|string> $resourceIds */
        $resourceIds = $request->validated('resource_ids') ?? [];
        /** @var string|null $date */
        $date = $request->validated('date');

        if ($service->booking_type->usesSessions()) {
            return response()->json([
                'slots' => [],
                'sessions' => $availability->sessions($service, $location, $quantity)
                    ->filter(fn (ServiceSession $session) => ! $keepRules || $rules->allowsStart($service, $session->starts_at))
                    ->values()
                    ->map(fn (ServiceSession $session) => [
                        'id' => $session->id,
                        'when' => $session->starts_at->copy()->setTimezone($availability->timezoneFor($session->location))->format('D j M Y, H:i'),
                        'places_left' => $availability->placesLeft($session),
                    ])->values(),
            ]);
        }

        $slots = $date === null ? [] : $availability->slots(
            $service,
            $location,
            $date,
            $quantity,
            array_map(intval(...), $optionIds),
            array_map(intval(...), $resourceIds),
        );

        // Times the rules don't allow aren't offered, unless staff have chosen to ignore them
        $slots = array_values(array_filter($slots, fn (BookingSlot $slot) => ! $keepRules || $rules->allowsStart($service, $slot->startsAt)));

        return response()->json([
            'slots' => array_map(fn (BookingSlot $slot) => [
                'time' => $slot->startsAt->format('H:i'),
                'resources' => array_map(fn (BookableResource $resource) => $resource->name, $slot->resources),
            ], $slots),
            'sessions' => [],
        ]);
    }
}
