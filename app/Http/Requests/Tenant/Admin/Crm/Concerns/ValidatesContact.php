<?php

namespace App\Http\Requests\Tenant\Admin\Crm\Concerns;

use App\Models\Crm\Contact;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesContact
{
    use ValidatesAddresses;

    /**
     * Drop addresses with every field blank, so the form can send empty address rows
     */
    protected function prepareForValidation(): void
    {
        $addresses = $this->input('addresses');

        if (is_array($addresses)) {
            $this->merge([
                'addresses' => array_values(array_filter($addresses, fn (mixed $address) => ! $this->isBlankAddress($address))),
            ]);
        }
    }

    /**
     * The shared validation rules for creating or updating a contact.
     *
     * @return array<string, mixed>
     */
    protected function contactRules(?Contact $contact = null): array
    {
        return [
            'title' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Unique across every contact, including deleted ones
            'email' => ['required', 'email', 'max:255', Rule::unique('crm_contacts', 'email')->ignore($contact?->id)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'is_spam' => ['boolean'],
            'marketing_email' => ['boolean'],
            'marketing_sms' => ['boolean'],
            'marketing_telephone' => ['boolean'],

            'addresses' => ['nullable', 'array', 'max:10', $this->distinctAddressesRule()],
            'addresses.*' => ['array'],
            ...$this->addressRules('addresses.*'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('crm.contacts.validation.email_taken'),
        ];
    }

    /**
     * The same address (postcode and first line) cannot be added to a contact twice
     */
    protected function distinctAddressesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $keys = collect($value)
                ->filter(fn (mixed $address) => is_array($address))
                ->map(fn (array $address) => Str::lower(trim((string) ($address['postcode'] ?? '')).'|'.trim((string) ($address['line_1'] ?? ''))));

            if ($keys->duplicates()->isNotEmpty()) {
                $fail(__('crm.contacts.validation.duplicate_address'));
            }
        };
    }

    /**
     * The validated contact attributes, without the addresses
     *
     * @return array<string, mixed>
     */
    public function contactAttributes(): array
    {
        return $this->safe()->except('addresses');
    }

    /**
     * The validated addresses, each limited to the address fields
     *
     * @return list<array<string, mixed>>
     */
    public function addressesAttributes(): array
    {
        /** @var list<array<string, mixed>> $addresses */
        $addresses = $this->validated('addresses') ?? [];

        return array_map(fn (array $address) => $this->addressFields($address), $addresses);
    }
}
