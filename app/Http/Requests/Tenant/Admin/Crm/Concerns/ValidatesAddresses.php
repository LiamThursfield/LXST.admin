<?php

namespace App\Http\Requests\Tenant\Admin\Crm\Concerns;

use App\Models\Crm\Address;

/**
 * Shared rules for the UK-style address sub-form (resources/js/tenant/components/crm/AddressFields.vue)
 */
trait ValidatesAddresses
{
    /**
     * The validation rules for one address, at the given attribute path (e.g. `address` or `addresses.*`)
     *
     * @return array<string, mixed>
     */
    protected function addressRules(string $path): array
    {
        return [
            "{$path}.postcode" => ['required', 'string', 'max:10'],
            "{$path}.line_1" => ['required', 'string', 'max:255'],
            "{$path}.line_2" => ['nullable', 'string', 'max:255'],
            "{$path}.line_3" => ['nullable', 'string', 'max:255'],
            "{$path}.town_city" => ['nullable', 'string', 'max:255'],
            "{$path}.county" => ['nullable', 'string', 'max:255'],
            "{$path}.country" => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Whether every address field is blank, which the forms use to mean "no address"
     */
    protected function isBlankAddress(mixed $address): bool
    {
        return is_array($address)
            && collect(Address::FIELDS)->every(fn (string $field) => blank($address[$field] ?? null));
    }

    /**
     * Keeps only the address fields of a validated address
     *
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function addressFields(array $address): array
    {
        return collect($address)->only(Address::FIELDS)->toArray();
    }
}
