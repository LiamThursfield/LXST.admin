<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\TimeOff;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeOffResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var TimeOff $timeOff */
        $timeOff = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('review', $timeOff)) {
            $actions[] = Action::make(__('bookings.time_off.actions.approve'), route('admin.bookings.time-off.approve', $timeOff))
                ->icon('i-lucide-check')
                ->method('POST');
            $actions[] = Action::make(__('bookings.time_off.actions.decline'), route('admin.bookings.time-off.decline', $timeOff))
                ->icon('i-lucide-x')
                ->method('POST');
        }

        if ($user?->can('delete', $timeOff)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.bookings.time-off.destroy', $timeOff))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('bookings.time_off.confirm_delete'));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TimeOff $timeOff */
        $timeOff = $this->resource;

        return $this->appendActions($request, [
            'id' => $timeOff->id,
            'resource_name' => $timeOff->resource?->name,
            'type_label' => $timeOff->type->label(),
            'status' => $timeOff->status->value,
            'status_label' => $timeOff->status->label(),
            'starts_on' => $timeOff->starts_on->toDateString(),
            'ends_on' => $timeOff->ends_on->toDateString(),
            'reason' => $timeOff->reason,
        ]);
    }
}
