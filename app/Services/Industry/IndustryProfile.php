<?php

namespace App\Services\Industry;

use App\Enums\BookingType;
use App\Enums\TenantModule;

/**
 * One industry from config/industries.php, and what a tenant in it starts with
 */
readonly class IndustryProfile
{
    /**
     * @param  list<TenantModule>  $suggestedModules
     * @param  list<BookingType>  $bookingTypes
     * @param  list<string>  $customerFields  Keys of config('industries.customer_fields')
     * @param  list<string>  $catalogueTags  Keys of config('catalogue.tag_presets') it suggests
     */
    public function __construct(
        public string $key,
        public array $suggestedModules,
        public array $bookingTypes,
        public array $customerFields,
        public array $catalogueTags = [],
    ) {}

    public function name(): string
    {
        return (string) __("industries.names.{$this->key}");
    }

    public function description(): string
    {
        return (string) __("industries.descriptions.{$this->key}");
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toSelectItem(): array
    {
        return ['value' => $this->key, 'label' => $this->name()];
    }
}
