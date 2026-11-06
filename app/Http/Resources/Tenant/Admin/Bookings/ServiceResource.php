<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Enums\Bookings\DepositType;
use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Crm\OrganisationUnit;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $service)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.bookings.services.show', $service))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $service)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.bookings.services.edit', $service))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $service)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.bookings.services.destroy', $service))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('bookings.services.confirm_delete', ['name' => $service->name]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array. Amounts are in major units, e.g. pounds, as the form takes them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;

        return $this->appendActions($request, [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'category_id' => $service->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $service->category?->name),
            'image_file_id' => $service->image_file_id,
            'booking_type' => $service->booking_type->value,
            'booking_type_label' => $service->booking_type->label(),
            'price_type' => $service->price_type->value,
            'price' => $service->price === null ? null : $this->toMajorUnits($service->price),
            'duration_minutes' => $service->duration_minutes,
            'buffer_before_minutes' => $service->buffer_before_minutes,
            'buffer_after_minutes' => $service->buffer_after_minutes,
            'capacity' => $service->capacity,
            'min_party_size' => $service->min_party_size,
            'max_party_size' => $service->max_party_size,
            'hire_unit' => $service->hire_unit?->value,
            'min_hire_units' => $service->min_hire_units,
            'max_hire_units' => $service->max_hire_units,
            'travel_minutes' => $service->travel_minutes,
            'required_resource_types' => $service->required_resource_types,
            'is_bookable_online' => $service->is_bookable_online,
            'min_notice_minutes' => $service->min_notice_minutes,
            'max_advance_days' => $service->max_advance_days,
            'cancellation_hours' => $service->cancellation_hours,
            'max_per_customer' => $service->max_per_customer,
            'requires_approval' => $service->requires_approval,
            'payment_requirement' => $service->payment_requirement->value,
            'deposit_type' => $service->deposit_type?->value,
            'deposit_amount' => $service->deposit_amount === null ? null
                : ($service->deposit_type === DepositType::Percent ? (string) $service->deposit_amount : $this->toMajorUnits($service->deposit_amount)),
            'options' => $this->whenLoaded('options', fn () => $service->options->map(fn (ServiceOption $option) => [
                'id' => $option->id,
                'type' => $option->type->value,
                'name' => $option->name,
                'price_adjustment' => $this->toMajorUnits($option->price_adjustment),
                'duration_adjustment' => $option->duration_adjustment,
            ])->values()),
            'resource_names' => $this->whenLoaded('resources', fn () => $service->resources->pluck('name')->values()),
            'location_ids' => $this->whenLoaded('locations', fn () => $service->locations->map(fn (OrganisationUnit $location) => $location->id)->values()),
        ]);
    }

    protected function toMajorUnits(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
