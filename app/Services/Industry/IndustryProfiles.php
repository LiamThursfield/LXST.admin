<?php

namespace App\Services\Industry;

use App\Enums\BookingType;
use App\Enums\TenantModule;

/**
 * The industries a tenant can choose from, as listed in config/industries.php
 */
class IndustryProfiles
{
    /**
     * @return list<string>
     */
    public function keys(): array
    {
        /** @var array<string, mixed> $profiles */
        $profiles = config('industries.profiles');

        return array_keys($profiles);
    }

    /**
     * @return list<IndustryProfile>
     */
    public function all(): array
    {
        return array_map(fn (string $key) => $this->find($key), $this->keys());
    }

    /**
     * Returns the industry's profile. An unknown key gets the fallback's, so removing an industry
     * from the list doesn't break the tenants still using it.
     */
    public function find(?string $key): IndustryProfile
    {
        /** @var array<string, array{suggested_modules: list<TenantModule>, booking_types: list<BookingType>, customer_fields: list<string>, catalogue_tags?: list<string>}> $profiles */
        $profiles = config('industries.profiles');
        $key = $key !== null && isset($profiles[$key]) ? $key : $this->fallbackKey();
        $profile = $profiles[$key];

        return new IndustryProfile(
            key: $key,
            suggestedModules: $profile['suggested_modules'],
            bookingTypes: $profile['booking_types'],
            customerFields: $profile['customer_fields'],
            catalogueTags: $profile['catalogue_tags'] ?? [],
        );
    }

    public function fallbackKey(): string
    {
        return (string) config('industries.fallback');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function asSelectItems(): array
    {
        return array_map(fn (IndustryProfile $profile) => $profile->toSelectItem(), $this->all());
    }

    /**
     * Every customer field a tenant can choose from, whichever industry it's in
     *
     * @return list<array{value: string, label: string, sensitive: bool}>
     */
    public function customerFieldItems(): array
    {
        /** @var array<string, array{sensitive: bool}> $fields */
        $fields = config('industries.customer_fields');

        return array_map(fn (string $key) => [
            'value' => $key,
            'label' => (string) __("industries.customer_fields.{$key}"),
            'sensitive' => $fields[$key]['sensitive'],
        ], array_keys($fields));
    }
}
