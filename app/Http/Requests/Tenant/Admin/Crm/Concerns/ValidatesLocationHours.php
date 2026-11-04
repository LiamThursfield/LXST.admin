<?php

namespace App\Http\Requests\Tenant\Admin\Crm\Concerns;

use App\Enums\Crm\LocationType;
use App\Support\TimePeriods;
use Closure;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;

/**
 * Shared rules for a location's type, weekly hours and exceptions
 */
trait ValidatesLocationHours
{
    /**
     * @return array<string, mixed>
     */
    protected function locationRules(): array
    {
        return [
            'location_type' => ['nullable', Rule::enum(LocationType::class)],
            'timezone' => ['nullable', 'timezone:all'],
            'service_areas' => ['nullable', 'array', 'max:200'],
            'service_areas.*' => ['nullable', 'string', 'max:100'],

            'opening_hours' => ['nullable', 'array', 'max:100', $this->weeklyHoursRule()],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            ...$this->periodRules('opening_hours.*'),

            'opening_hour_exceptions' => ['nullable', 'array', 'max:200', $this->exceptionDatesRule()],
            'opening_hour_exceptions.*.name' => ['required', 'string', 'max:255'],
            'opening_hour_exceptions.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'opening_hour_exceptions.*.ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:opening_hour_exceptions.*.starts_on'],
            'opening_hour_exceptions.*.periods' => ['nullable', 'array', 'max:10', $this->exceptionPeriodsRule()],
            ...$this->periodRules('opening_hour_exceptions.*.periods.*'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodRules(string $path): array
    {
        return [
            "{$path}.opens_at" => ['required', 'date_format:H:i'],
            "{$path}.closes_at" => ['required', 'date_format:H:i'],
            "{$path}.label" => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Friendlier names for the location fields in validation messages
     *
     * @return array<string, string>
     */
    protected function locationAttributes(): array
    {
        $names = [
            'opens_at' => Lang::get('crm.opening_hours.fields.opens_at'),
            'closes_at' => Lang::get('crm.opening_hours.fields.closes_at'),
            'label' => Lang::get('crm.opening_hours.fields.label'),
        ];

        return [
            'location_type' => Lang::get('crm.opening_hours.fields.location_type'),
            'timezone' => Lang::get('crm.opening_hours.fields.timezone'),
            ...collect($names)->mapWithKeys(fn (string $name, string $field) => [
                "opening_hours.*.{$field}" => $name,
                "opening_hour_exceptions.*.periods.*.{$field}" => $name,
            ])->all(),
            'opening_hour_exceptions.*.name' => Lang::get('crm.opening_hours.fields.exception_name'),
            'opening_hour_exceptions.*.starts_on' => Lang::get('crm.opening_hours.fields.starts_on'),
            'opening_hour_exceptions.*.ends_on' => Lang::get('crm.opening_hours.fields.ends_on'),
        ];
    }

    /**
     * A day's periods can't overlap, or it isn't clear when it closes
     */
    protected function weeklyHoursRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            foreach (collect(is_array($value) ? $value : [])->groupBy('day_of_week') as $day => $periods) {
                $problem = TimePeriods::problem($periods->all());

                if ($problem !== null) {
                    $fail(__("crm.opening_hours.validation.{$problem}", ['when' => __("crm.opening_hours.days.{$day}")]));
                }
            }
        };
    }

    protected function exceptionPeriodsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $problem = TimePeriods::problem(is_array($value) ? $value : []);

            if ($problem !== null) {
                $fail(__("crm.opening_hours.validation.{$problem}", ['when' => __('crm.opening_hours.these_dates')]));
            }
        };
    }

    /**
     * Two exceptions on one date would leave it unclear which applies
     */
    protected function exceptionDatesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ranges = collect(is_array($value) ? $value : [])
                ->filter(fn (mixed $exception) => is_array($exception) && is_string($exception['starts_on'] ?? null))
                ->map(fn (array $exception) => [
                    'starts_on' => $exception['starts_on'],
                    'ends_on' => is_string($exception['ends_on'] ?? null) ? $exception['ends_on'] : $exception['starts_on'],
                    'name' => (string) ($exception['name'] ?? ''),
                ])
                ->sortBy('starts_on')
                ->values();

            foreach ($ranges as $index => $range) {
                $next = $ranges->get($index + 1);

                if ($next !== null && $next['starts_on'] <= $range['ends_on']) {
                    $fail(__('crm.opening_hours.validation.exceptions_overlap', ['first' => $range['name'], 'second' => $next['name']]));

                    return;
                }
            }
        };
    }

    /**
     * The location's type, timezone and service areas, all blank when the unit isn't a location
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function locationAttributesFrom(array $validated, bool $isLocation): array
    {
        if (! $isLocation) {
            return ['location_type' => null, 'timezone' => null, 'service_areas' => null];
        }

        $type = LocationType::tryFrom((string) ($validated['location_type'] ?? '')) ?? LocationType::Premises;
        /** @var list<string|null> $areas */
        $areas = $validated['service_areas'] ?? [];

        return [
            'location_type' => $type,
            'timezone' => $validated['timezone'] ?? null,
            'service_areas' => $type->hasServiceAreas() ? (array_values(array_filter($areas)) ?: null) : null,
        ];
    }

    /**
     * The validated weekly hours, ready to save
     *
     * @return list<array<string, mixed>>
     */
    public function openingHoursAttributes(): array
    {
        /** @var list<array<string, mixed>> $hours */
        $hours = $this->validated('opening_hours') ?? [];

        return array_map(fn (array $hour) => [
            'day_of_week' => (int) $hour['day_of_week'],
            ...$this->periodAttributes($hour),
        ], $hours);
    }

    /**
     * The validated exceptions, ready to save. No end date means one day, and no periods means closed.
     *
     * @return list<array<string, mixed>>
     */
    public function openingHourExceptionAttributes(): array
    {
        /** @var list<array<string, mixed>> $exceptions */
        $exceptions = $this->validated('opening_hour_exceptions') ?? [];

        return array_map(function (array $exception) {
            /** @var list<array<string, mixed>> $periods */
            $periods = $exception['periods'] ?? [];

            return [
                'name' => $exception['name'],
                'starts_on' => $exception['starts_on'],
                'ends_on' => $exception['ends_on'] ?? $exception['starts_on'],
                'periods' => array_map($this->periodAttributes(...), $periods) ?: null,
            ];
        }, $exceptions);
    }

    /**
     * @param  array<string, mixed>  $period
     * @return array{opens_at: mixed, closes_at: mixed, label: mixed}
     */
    protected function periodAttributes(array $period): array
    {
        return [
            'opens_at' => $period['opens_at'],
            'closes_at' => $period['closes_at'],
            'label' => $period['label'] ?? null,
        ];
    }
}
