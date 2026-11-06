<?php

namespace App\Http\Requests\Tenant\Admin\Bookings\Concerns;

use App\Enums\Bookings\DepositType;
use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\PaymentRequirement;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Details that don't apply to the chosen booking type are ignored rather than checked, so switching
 * type in the form can't fail on a field that's no longer shown.
 */
trait ValidatesService
{
    /** Prices are entered in major units, e.g. pounds, up to this much */
    protected const int MAX_PRICE = 1000000;

    /**
     * The shared validation rules for creating or updating a service.
     *
     * @return array<string, mixed>
     */
    protected function serviceRules(?Service $service = null): array
    {
        $image = Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'));

        return [
            'name' => ['required', 'string', 'max:120'],
            // Unique across every service, including deleted ones
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('bookings_services', 'slug')->ignore($service?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists(ServiceCategory::class, 'id')],
            'image_file_id' => ['nullable', 'integer', $image],
            'booking_type' => ['required', Rule::enum(BookingType::class)],
            'is_bookable_online' => ['boolean'],
            'min_notice_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'max_advance_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'max_per_customer' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_approval' => ['boolean'],
            'payment_requirement' => ['nullable', Rule::enum(PaymentRequirement::class)],
            'deposit_type' => [Rule::excludeIf(! $this->wantsDeposit()), 'required', Rule::enum(DepositType::class)],
            'deposit_amount' => [
                Rule::excludeIf(! $this->wantsDeposit()),
                'required',
                'numeric',
                'gt:0',
                $this->input('deposit_type') === DepositType::Percent->value ? 'max:100' : 'max:'.self::MAX_PRICE,
            ],
            ...$this->feeRules('no_show_fee'),
            ...$this->feeRules('late_cancellation_fee'),

            'price_type' => ['required', Rule::enum(ServicePriceType::class)],
            'price' => [Rule::excludeIf(! $this->priceHasAmount()), 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],

            'duration_minutes' => [$this->excludeUnlessUsing(BookingType::DETAIL_DURATION), 'required', 'integer', 'min:1', 'max:1440'],
            'buffer_before_minutes' => [$this->excludeUnlessUsing(BookingType::DETAIL_BUFFERS), 'nullable', 'integer', 'min:0', 'max:1440'],
            'buffer_after_minutes' => [$this->excludeUnlessUsing(BookingType::DETAIL_BUFFERS), 'nullable', 'integer', 'min:0', 'max:1440'],
            'capacity' => [$this->excludeUnlessUsing(BookingType::DETAIL_CAPACITY), 'required', 'integer', 'min:1', 'max:100000'],
            'min_party_size' => [$this->excludeUnlessUsing(BookingType::DETAIL_PARTY_SIZE), 'required', 'integer', 'min:1', 'max:10000'],
            'max_party_size' => [$this->excludeUnlessUsing(BookingType::DETAIL_PARTY_SIZE), 'required', 'integer', 'gte:min_party_size', 'max:10000'],
            'hire_unit' => [$this->excludeUnlessUsing(BookingType::DETAIL_HIRE), 'required', Rule::enum(HireUnit::class)],
            'min_hire_units' => [$this->excludeUnlessUsing(BookingType::DETAIL_HIRE), 'required', 'integer', 'min:1', 'max:10000'],
            'max_hire_units' => [$this->excludeUnlessUsing(BookingType::DETAIL_HIRE), 'nullable', 'integer', 'gte:min_hire_units', 'max:10000'],
            'travel_minutes' => [$this->excludeUnlessUsing(BookingType::DETAIL_TRAVEL), 'nullable', 'integer', 'min:0', 'max:1440'],

            'required_resource_types' => [$this->excludeUnlessUsing(BookingType::DETAIL_RESOURCES), 'nullable', 'array'],
            'required_resource_types.*' => [$this->excludeUnlessUsing(BookingType::DETAIL_RESOURCES), 'distinct', Rule::enum(ResourceType::class)],

            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['array'],
            'options.*.id' => ['nullable', 'integer', Rule::exists('bookings_service_options', 'id')->where('service_id', $service->id ?? 0)],
            'options.*.type' => ['required', Rule::enum(ServiceOptionType::class)],
            'options.*.name' => ['required', 'string', 'max:120'],
            'options.*.price_adjustment' => ['nullable', 'numeric', 'decimal:0,2', 'min:-'.self::MAX_PRICE, 'max:'.self::MAX_PRICE],
            'options.*.duration_adjustment' => ['nullable', 'integer', 'min:-1440', 'max:1440'],

            // Locations are part of the CRM, so without it a service's are left as they are
            'location_ids' => [Rule::excludeIf(! $this->tenantHasLocations()), 'nullable', 'array'],
            'location_ids.*' => [
                Rule::excludeIf(! $this->tenantHasLocations()),
                'integer',
                'distinct',
                Rule::exists(OrganisationUnit::class, 'id')->where('type', OrganisationUnitType::Location->value),
            ],
        ];
    }

    /**
     * Fields named as in the form, e.g. "The Name field is required" rather than "The options.0.name
     * field is required"
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'options.*.type' => __('bookings.services.options.type'),
            'options.*.name' => __('bookings.services.options.name'),
            'options.*.price_adjustment' => __('bookings.services.options.price_adjustment'),
            'options.*.duration_adjustment' => __('bookings.services.options.duration_adjustment'),
            'location_ids.*' => __('bookings.services.fields.locations'),
        ];
    }

    /**
     * The validated service attributes, with whatever doesn't apply to its booking type cleared
     *
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        $attributes = $this->safe()->except(['options', 'location_ids']);

        return [
            ...$attributes,
            'price' => isset($attributes['price']) ? $this->toMinorUnits($attributes['price']) : null,
            'duration_minutes' => $attributes['duration_minutes'] ?? null,
            'buffer_before_minutes' => (int) ($attributes['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($attributes['buffer_after_minutes'] ?? 0),
            'capacity' => $attributes['capacity'] ?? null,
            'min_party_size' => $attributes['min_party_size'] ?? null,
            'max_party_size' => $attributes['max_party_size'] ?? null,
            'hire_unit' => $attributes['hire_unit'] ?? null,
            'min_hire_units' => $attributes['min_hire_units'] ?? null,
            'max_hire_units' => $attributes['max_hire_units'] ?? null,
            'travel_minutes' => $attributes['travel_minutes'] ?? null,
            'required_resource_types' => ($attributes['required_resource_types'] ?? []) ?: null,
            'payment_requirement' => $attributes['payment_requirement'] ?? PaymentRequirement::None->value,
            'deposit_type' => $attributes['deposit_type'] ?? null,
            // A fixed deposit is entered in pounds and kept in pence; a percentage is kept as it is
            'deposit_amount' => isset($attributes['deposit_amount'])
                ? (($attributes['deposit_type'] ?? null) === DepositType::Percent->value ? (int) round((float) $attributes['deposit_amount']) : $this->toMinorUnits($attributes['deposit_amount']))
                : null,
            ...$this->feeAttributes('no_show_fee', $attributes),
            ...$this->feeAttributes('late_cancellation_fee', $attributes),
        ];
    }

    /**
     * A no-show or late cancellation fee: blank for none, or a set amount or a percentage of the price
     *
     * @return array<string, mixed>
     */
    protected function feeRules(string $fee): array
    {
        $isPercent = $this->input("{$fee}_type") === DepositType::Percent->value;

        return [
            "{$fee}_type" => ["required_with:{$fee}_amount", 'nullable', Rule::enum(DepositType::class)],
            "{$fee}_amount" => ['nullable', 'numeric', 'gt:0', $isPercent ? 'max:100' : 'max:'.self::MAX_PRICE],
        ];
    }

    /**
     * A fixed fee is entered in pounds and kept in pence; a percentage is kept as it is
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function feeAttributes(string $fee, array $attributes): array
    {
        $amount = $attributes["{$fee}_amount"] ?? null;
        $isPercent = ($attributes["{$fee}_type"] ?? null) === DepositType::Percent->value;

        return [
            "{$fee}_type" => $amount === null ? null : ($attributes["{$fee}_type"] ?? DepositType::Fixed->value),
            "{$fee}_amount" => $amount === null ? null : ($isPercent ? (int) round((float) $amount) : $this->toMinorUnits($amount)),
        ];
    }

    /**
     * The validated options in their submitted order
     *
     * @return list<array{id: int|null, type: string, name: string, price_adjustment: int, duration_adjustment: int, order: int}>
     */
    public function optionsAttributes(): array
    {
        /** @var list<array<string, mixed>> $options */
        $options = $this->validated('options') ?? [];

        return array_map(fn (array $option, int $order) => [
            'id' => isset($option['id']) ? (int) $option['id'] : null,
            'type' => (string) $option['type'],
            'name' => (string) $option['name'],
            'price_adjustment' => $this->toMinorUnits($option['price_adjustment'] ?? 0),
            'duration_adjustment' => (int) ($option['duration_adjustment'] ?? 0),
            'order' => $order,
        ], $options, array_keys($options));
    }

    /**
     * The chosen locations, or null when the tenant's locations aren't available to change
     *
     * @return list<int>|null
     */
    public function locationIds(): ?array
    {
        if (! $this->tenantHasLocations()) {
            return null;
        }

        /** @var list<int|string> $ids */
        $ids = $this->validated('location_ids') ?? [];

        return array_map(intval(...), $ids);
    }

    protected function wantsDeposit(): bool
    {
        return $this->input('payment_requirement') === PaymentRequirement::Deposit->value;
    }

    protected function excludeUnlessUsing(string $detail): mixed
    {
        $type = BookingType::tryFrom((string) $this->input('booking_type'));

        return Rule::excludeIf(! ($type?->uses($detail) ?? false));
    }

    protected function priceHasAmount(): bool
    {
        return ServicePriceType::tryFrom((string) $this->input('price_type'))?->hasAmount() ?? false;
    }

    protected function tenantHasLocations(): bool
    {
        return tenant()?->hasModule(TenantModule::Crm) ?? false;
    }

    /**
     * Pounds to pence, through round() as 19.99 * 100 is just under 1999 as a float
     */
    protected function toMinorUnits(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
