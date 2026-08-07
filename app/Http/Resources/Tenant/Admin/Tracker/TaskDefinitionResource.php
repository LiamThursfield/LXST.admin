<?php

namespace App\Http\Resources\Tenant\Admin\Tracker;

use App\Http\Resources\Traits\HasActions;
use App\Models\Tracker\TaskDefinition;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDefinitionResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var TaskDefinition $definition */
        $definition = $this->resource;

        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $definition)) {
            $actions[] = Action::make('Edit', route('admin.tracker.tasks.edit', $definition))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $definition)) {
            $actions[] = Action::make('Delete', route('admin.tracker.tasks.destroy', $definition))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation("Are you sure you want to delete task \"$definition->title\"?");
        }

        if (empty($actions) && $user?->can('view', $definition)) {
            $actions[] = Action::make('View', route('admin.tracker.tasks.show', $definition))
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
        /** @var TaskDefinition $definition */
        $definition = $this->resource;

        return $this->appendActions($request, [
            'id' => $definition->id,
            'title' => $definition->title,
            'description' => $definition->description,
            'category_id' => $definition->category_id,
            'category' => $this->whenLoaded('category', fn () => $definition->category?->name),
            'period' => $definition->period->value,
            'schedule' => $definition->schedule?->toArray(),
            'measure' => $definition->measure->value,
            'unit' => $definition->unit->value,
            'comparator' => $definition->comparator->value,
            'aggregation' => $definition->aggregation->value,
            // Targets are stored in the unit's base; expose them in the display unit.
            'target_min' => $this->displayTarget($definition->target_min),
            'target_max' => $this->displayTarget($definition->target_max),
            'stepped' => $definition->stepped,
            'counts_during_holiday' => $definition->counts_during_holiday,
            'active_from' => $definition->active_from?->toDateString(),
            'active_to' => $definition->active_to?->toDateString(),
            'status' => $definition->status->value,
            'sort_order' => $definition->sort_order,
        ]);
    }

    /**
     * Convert a stored base-unit target back into the definition's display unit.
     */
    protected function displayTarget(?string $base): ?float
    {
        if ($base === null) {
            return null;
        }

        /** @var TaskDefinition $definition */
        $definition = $this->resource;

        return $definition->unit->fromBase((float) $base);
    }
}
