<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Enums\Crm\OrganisationUnitType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\LocationResource;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lets a website show its locations and whether they're open. Not cached, as "open now" changes
 * with the clock rather than with the content.
 */
class LocationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LocationResource::collection(
            OrganisationUnit::query()
                ->where('type', OrganisationUnitType::Location)
                ->with(['address', 'openingHours', 'openingHourExceptions'])
                ->orderBy('name')
                ->get()
        );
    }

    public function show(OrganisationUnit $location): LocationResource
    {
        abort_unless($location->type === OrganisationUnitType::Location, 404);

        return new LocationResource($location->load(['address', 'openingHours', 'openingHourExceptions']));
    }
}
