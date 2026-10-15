<?php

namespace App\Http\Requests\Tenant\Admin\Tracker\Concerns;

use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Enums\Tracker\TrackerMeasure;
use App\Enums\Tracker\TrackerPeriod;
use App\Enums\Tracker\TrackerUnit;
use App\Models\Tracker\Category;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

trait ValidatesTaskDefinition
{
    /**
     * The shared validation rules for creating or updating a task definition.
     *
     * @return array<string, mixed>
     */
    protected function taskDefinitionRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', $this->categoryVisibleRule()],

            'period' => ['required', Rule::enum(TrackerPeriod::class)],
            'measure' => ['required', Rule::enum(TrackerMeasure::class)],
            'unit' => ['required', Rule::enum(TrackerUnit::class), $this->unitMatchesMeasureRule()],
            'comparator' => ['required', Rule::enum(TrackerComparator::class)],
            'aggregation' => ['required', Rule::enum(TrackerAggregation::class)],

            'target_min' => [$this->targetRequiredWhen('requiresMin'), 'nullable', 'numeric'],
            'target_max' => [$this->targetRequiredWhen('requiresMax'), 'nullable', 'numeric'],

            'stepped' => ['boolean'],
            'counts_during_holiday' => ['boolean'],

            'active_from' => ['required', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],

            'status' => ['required', Rule::enum(TaskDefinitionStatus::class)],

            'schedule' => ['nullable', 'array'],
            'schedule.daysOfWeek' => ['nullable', 'array'],
            'schedule.daysOfWeek.*' => ['integer', 'between:1,7'],
            'schedule.monthsOfYear' => ['nullable', 'array'],
            'schedule.monthsOfYear.*' => ['integer', 'between:1,12'],
            'schedule.daysOfMonth' => ['nullable', 'array'],
            'schedule.daysOfMonth.*' => ['integer', 'between:1,31'],
            'schedule.interval' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Require the target only when the chosen comparator needs that bound.
     *
     * @param  'requiresMin'|'requiresMax'  $method
     */
    protected function targetRequiredWhen(string $method): RequiredIf
    {
        return Rule::requiredIf(function () use ($method): bool {
            $comparator = TrackerComparator::tryFrom((string) $this->input('comparator'));

            return $comparator !== null && $comparator->{$method}();
        });
    }

    /**
     * Ensure the selected category is one the user is allowed to use.
     */
    protected function categoryVisibleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $user = $this->user();

            $visible = $user !== null
                && Category::query()->visibleTo($user)->whereKey($value)->exists();

            if (! $visible) {
                $fail('The selected category is invalid.');
            }
        };
    }

    /**
     * Ensure the selected unit belongs to the selected measure.
     */
    protected function unitMatchesMeasureRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unit = TrackerUnit::tryFrom((string) $value);
            $measure = TrackerMeasure::tryFrom((string) $this->input('measure'));

            if ($unit !== null && $measure !== null && $unit->measure() !== $measure) {
                $fail('The selected unit does not belong to the selected measure.');
            }
        };
    }
}
