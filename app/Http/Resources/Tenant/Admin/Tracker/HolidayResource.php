<?php

namespace App\Http\Resources\Tenant\Admin\Tracker;

use App\Http\Resources\Traits\HasActions;
use App\Models\Tracker\Holiday;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Holiday $holiday */
        $holiday = $this->resource;

        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $holiday)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.tracker.holidays.edit', $holiday))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $holiday)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.tracker.holidays.destroy', $holiday))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('tracker.holidays.confirm_delete'));
        }

        if (empty($actions) && $user?->can('view', $holiday)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.tracker.holidays.show', $holiday))
                ->icon('i-lucide-eye');
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
        /** @var Holiday $holiday */
        $holiday = $this->resource;

        return $this->appendActions($request, [
            'id' => $holiday->id,
            'label' => $holiday->label,
            'starts_on' => $holiday->starts_on->toDateString(),
            'ends_on' => $holiday->ends_on->toDateString(),
        ]);
    }
}
