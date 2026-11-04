<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use App\Services\Crm\LocationHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a location is and when it's open, for a website to show
 */
class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganisationUnit $location */
        $location = $this->resource;
        $hours = app(LocationHours::class);

        return [
            'slug' => $location->slug,
            'name' => $location->name,
            'location_type' => $location->location_type?->value,
            'timezone' => $location->localTimezone(),
            'email' => $location->email,
            'telephone' => $location->telephone,
            'address' => ($location->location_type?->hasPublicAddress() ?? true) ? $location->address?->only(Address::FIELDS) : null,
            'service_areas' => $location->service_areas ?? [],
            'opening_hours' => $hours->weeklyHours($location),
            'exceptions' => $hours->upcomingExceptions($location),
            // Right now, so this mustn't be cached
            'status' => $hours->status($location),
            'schema_org' => $hours->schemaOrg($location),
        ];
    }
}
