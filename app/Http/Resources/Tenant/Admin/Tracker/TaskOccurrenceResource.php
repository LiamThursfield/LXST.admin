<?php

namespace App\Http\Resources\Tenant\Admin\Tracker;

use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An occurrence with a summary of its task and its entries. All values are
 * converted from base units into the task's display unit.
 */
class TaskOccurrenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaskOccurrence $occurrence */
        $occurrence = $this->resource;

        /** @var TaskDefinition $definition */
        $definition = $occurrence->taskDefinition;

        return [
            'id' => $occurrence->id,
            'period_key' => $occurrence->period_key,
            'period_start' => $occurrence->period_start->toDateString(),
            'period_end' => $occurrence->period_end?->toDateString(),
            'status' => $occurrence->status->value,
            'is_holiday' => $occurrence->is_holiday,
            'computed_value' => $this->display($definition, $occurrence->computed_value),
            'definition' => [
                'id' => $definition->id,
                'title' => $definition->title,
                'period' => $definition->period->value,
                'measure' => $definition->measure->value,
                'unit' => $definition->unit->value,
                'comparator' => $definition->comparator->value,
                'aggregation' => $definition->aggregation->value,
                'target_min' => $this->display($definition, $definition->target_min),
                'target_max' => $this->display($definition, $definition->target_max),
                'stepped' => $definition->stepped,
                'category' => $definition->category === null ? null : [
                    'name' => $definition->category->name,
                    'colour' => $definition->category->colour,
                    'icon' => $definition->category->icon,
                ],
            ],
            'entries' => $this->whenLoaded('entries', fn () => $occurrence->entries
                ->sortBy('occurred_at')
                ->values()
                ->map(fn (TaskEntry $entry) => [
                    'id' => $entry->id,
                    'value' => $this->display($definition, $entry->value),
                    'label' => $entry->label,
                    'occurred_at' => $entry->occurred_at->toIso8601String(),
                ])),
        ];
    }

    /**
     * Convert a stored base-unit value into the definition's display unit.
     */
    protected function display(TaskDefinition $definition, ?string $base): ?float
    {
        if ($base === null) {
            return null;
        }

        return round($definition->unit->fromBase((float) $base), 4);
    }
}
