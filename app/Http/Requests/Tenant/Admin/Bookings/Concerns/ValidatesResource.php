<?php

namespace App\Http\Requests\Tenant\Admin\Bookings\Concerns;

use App\Enums\Bookings\ResourceType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Models\User;
use App\Support\TimePeriods;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Details that don't apply to the chosen type are ignored rather than checked, and cleared on save
 */
trait ValidatesResource
{
    /**
     * The shared validation rules for creating or updating a resource.
     *
     * @return array<string, mixed>
     */
    protected function resourceRules(?BookableResource $resource = null): array
    {
        $image = Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'));
        $type = ResourceType::tryFrom((string) $this->input('type'));

        return [
            // A person can't become a space or the like, as they're in different lists
            'type' => ['required', Rule::enum(ResourceType::class), Rule::in($this->allowedTypes())],
            'name' => ['required', 'string', 'max:120'],
            // Unique across every resource, including deleted ones
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('bookings_resources', 'slug')->ignore($resource?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_file_id' => ['nullable', 'integer', $image],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],

            'user_id' => [
                Rule::excludeIf(! ($type?->isPerson() ?? false)),
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique('bookings_resources', 'user_id')->ignore($resource?->id),
            ],
            'job_title' => [Rule::excludeIf(! ($type?->isPerson() ?? false)), 'nullable', 'string', 'max:120'],
            'capacity' => [Rule::excludeIf(! ($type?->hasCapacity() ?? false)), 'nullable', 'integer', 'min:1', 'max:100000'],
            'combine_group' => [Rule::excludeIf(! ($type?->hasCapacity() ?? false)), 'nullable', 'string', 'max:60'],

            // Locations are part of the CRM, so without it a resource's is left as it is
            'location_id' => [
                Rule::excludeIf(! $this->tenantHasLocations()),
                'nullable',
                'integer',
                Rule::exists(OrganisationUnit::class, 'id')->where('type', OrganisationUnitType::Location->value),
            ],

            'working_hours' => ['nullable', 'array', 'max:100', $this->workingHoursRule()],
            'working_hours.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'working_hours.*.starts_at' => ['required', 'date_format:H:i'],
            'working_hours.*.ends_at' => ['required', 'date_format:H:i'],

            'services' => ['nullable', 'array'],
            'services.*.id' => ['required', 'integer', 'distinct', Rule::exists(Service::class, 'id')->withoutTrashed()],
            'services.*.price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedTypes(): array
    {
        $forStaff = $this->routeIs('admin.bookings.staff.*');

        return array_values(array_map(
            fn (ResourceType $type) => $type->value,
            array_filter(ResourceType::cases(), fn (ResourceType $type) => $type->isPerson() === $forStaff),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'working_hours.*.starts_at' => __('bookings.resources.fields.starts_at'),
            'working_hours.*.ends_at' => __('bookings.resources.fields.ends_at'),
            'services.*.price' => __('bookings.resources.services.price'),
            'services.*.duration_minutes' => __('bookings.resources.services.duration_minutes'),
        ];
    }

    /**
     * A day's periods can't overlap
     */
    protected function workingHoursRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            foreach (collect(is_array($value) ? $value : [])->groupBy('day_of_week') as $day => $periods) {
                $problem = TimePeriods::problem($periods->all(), 'starts_at', 'ends_at');

                if ($problem !== null) {
                    $fail(__("bookings.schedule.validation.{$problem}", ['when' => __("crm.opening_hours.days.{$day}")]));
                }
            }
        };
    }

    /**
     * The validated resource attributes, with what doesn't apply to its type cleared
     *
     * @return array<string, mixed>
     */
    public function resourceAttributes(): array
    {
        $attributes = $this->safe()->except(['working_hours', 'services']);

        return [
            ...$attributes,
            'user_id' => $attributes['user_id'] ?? null,
            'job_title' => $attributes['job_title'] ?? null,
            'capacity' => $attributes['capacity'] ?? null,
            'combine_group' => filled($attributes['combine_group'] ?? null) ? trim((string) $attributes['combine_group']) : null,
        ];
    }

    /**
     * @return list<array{day_of_week: int, starts_at: string, ends_at: string}>
     */
    public function workingHoursAttributes(): array
    {
        /** @var list<array<string, mixed>> $hours */
        $hours = $this->validated('working_hours') ?? [];

        return array_map(fn (array $hour) => [
            'day_of_week' => (int) $hour['day_of_week'],
            'starts_at' => (string) $hour['starts_at'],
            'ends_at' => (string) $hour['ends_at'],
        ], $hours);
    }

    /**
     * The chosen services by id, with any price (in minor units) or duration of the resource's own
     *
     * @return array<int, array{price: int|null, duration_minutes: int|null}>
     */
    public function servicesAttributes(): array
    {
        /** @var list<array<string, mixed>> $services */
        $services = $this->validated('services') ?? [];

        return collect($services)->mapWithKeys(fn (array $service) => [
            (int) $service['id'] => [
                'price' => is_numeric($service['price'] ?? null) ? (int) round((float) $service['price'] * 100) : null,
                'duration_minutes' => is_numeric($service['duration_minutes'] ?? null) ? (int) $service['duration_minutes'] : null,
            ],
        ])->all();
    }

    protected function tenantHasLocations(): bool
    {
        return tenant()?->hasModule(TenantModule::Crm) ?? false;
    }
}
