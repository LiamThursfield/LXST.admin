<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\WorkingHour;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var BookableResource $resource */
        $resource = $this->resource;
        $user = $request->user();
        $actions = [];
        $routes = $resource->type->isPerson() ? 'admin.bookings.staff' : 'admin.bookings.resources';

        if ($user?->can('view', $resource)) {
            $actions[] = Action::make(__('common.actions.view'), route("{$routes}.show", $resource))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $resource)) {
            $actions[] = Action::make(__('common.actions.edit'), route("{$routes}.edit", $resource))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $resource)) {
            $actions[] = Action::make(__('common.actions.delete'), route("{$routes}.destroy", $resource))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('bookings.resources.confirm_delete', ['name' => $resource->name]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array. Prices are in major units, e.g. pounds, as the form takes them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BookableResource $resource */
        $resource = $this->resource;

        return $this->appendActions($request, [
            'id' => $resource->id,
            'type' => $resource->type->value,
            'type_label' => $resource->type->label(),
            'name' => $resource->name,
            'slug' => $resource->slug,
            'description' => $resource->description,
            'image_file_id' => $resource->image_file_id,
            'location_id' => $resource->location_id,
            'location_name' => $this->whenLoaded('location', fn () => $resource->location?->name),
            'user_id' => $resource->user_id,
            'job_title' => $resource->job_title,
            'capacity' => $resource->capacity,
            'is_active' => $resource->is_active,
            'is_public' => $resource->is_public,
            'services_count' => $resource->services_count ?? null,
            'working_hours' => $this->whenLoaded('workingHours', fn () => $resource->workingHours
                ->sortBy(['day_of_week', 'starts_at'])
                ->map(fn (WorkingHour $hour) => $hour->only(['day_of_week', 'starts_at', 'ends_at']))
                ->values()),
            'services' => $this->whenLoaded('services', fn () => $resource->services->map(function (Service $service) {
                /** @var object{price: int|null, duration_minutes: int|null} $pivot */
                $pivot = $service->getRelation('pivot');

                return [
                    'id' => $service->id,
                    'price' => $pivot->price === null ? null : number_format($pivot->price / 100, 2, '.', ''),
                    'duration_minutes' => $pivot->duration_minutes,
                ];
            })->values()),
        ]);
    }
}
