<?php

namespace App\Http\Requests\Tenant\Admin\Crm\Concerns;

use App\Enums\Crm\OrganisationUnitType;
use App\Enums\Crm\SocialMedia;
use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesOrganisationUnit
{
    /**
     * Treat an address with every field blank as "no address", so the form can always send the address fields
     */
    protected function prepareForValidation(): void
    {
        $address = $this->input('address');

        if (is_array($address) && collect(Address::FIELDS)->every(fn (string $field) => blank($address[$field] ?? null))) {
            $this->merge(['address' => null]);
        }
    }

    /**
     * The shared validation rules for creating or updating an organisation unit.
     *
     * @return array<string, mixed>
     */
    protected function organisationUnitRules(?OrganisationUnit $organisationUnit = null): array
    {
        return [
            'type' => ['required', Rule::enum(OrganisationUnitType::class), $this->typeChangeRule($organisationUnit)],
            'parent_id' => [
                Rule::requiredIf(fn () => $this->type()?->requiresParent() ?? false),
                Rule::prohibitedIf(fn () => $this->type() === OrganisationUnitType::Company),
                'nullable',
                'integer',
                Rule::exists('crm_organisation_units', 'id'),
                $this->parentRule(),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('crm_organisation_units', 'slug')->ignore($organisationUnit?->id),
            ],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],

            'socials' => ['nullable', 'array:'.implode(',', array_column(SocialMedia::cases(), 'value'))],
            'socials.*' => ['nullable', 'string', 'max:255'],

            'notification_emails' => ['nullable', 'array'],
            'notification_emails.*' => ['email', 'max:255'],

            'address' => ['nullable', 'array'],
            'address.postcode' => ['required_with:address', 'string', 'max:10'],
            'address.line_1' => ['required_with:address', 'string', 'max:255'],
            'address.line_2' => ['nullable', 'string', 'max:255'],
            'address.line_3' => ['nullable', 'string', 'max:255'],
            'address.town_city' => ['nullable', 'string', 'max:255'],
            'address.county' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The submitted type, if valid
     */
    protected function type(): ?OrganisationUnitType
    {
        $type = $this->input('type');

        return is_string($type) ? OrganisationUnitType::tryFrom($type) : null;
    }

    /**
     * The parent must be of the type allowed for the unit's type: a location is part of a company,
     * and a department is part of a location
     */
    protected function parentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = $this->type();
            $parent = is_numeric($value) ? OrganisationUnit::query()->find((int) $value) : null;

            if ($type === null || $parent === null) {
                return;
            }

            if (! in_array($parent->type, $type->allowedParentTypes(), true)) {
                $fail(__('crm.organisation_units.validation.parent_type', [
                    'type' => $type->label(),
                    'parents' => collect($type->allowedParentTypes())->map->label()->implode(', '),
                ]));
            }
        };
    }

    /**
     * A unit that has units within it cannot change type, as they would no longer be under an allowed parent
     */
    protected function typeChangeRule(?OrganisationUnit $organisationUnit): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($organisationUnit): void {
            if ($organisationUnit === null || $value === $organisationUnit->type->value) {
                return;
            }

            if ($organisationUnit->children()->exists()) {
                $fail(__('crm.organisation_units.validation.type_has_children'));
            }
        };
    }

    /**
     * The validated organisation unit attributes, with blank socials removed
     *
     * @return array<string, mixed>
     */
    public function organisationUnitAttributes(): array
    {
        $attributes = $this->safe()->except('address');

        /** @var array<string, string|null> $socials */
        $socials = $attributes['socials'] ?? [];
        /** @var list<string|null> $notificationEmails */
        $notificationEmails = $attributes['notification_emails'] ?? [];

        $attributes['socials'] = array_filter($socials) ?: null;
        $attributes['notification_emails'] = array_values(array_filter($notificationEmails)) ?: null;

        return $attributes;
    }

    /**
     * The validated address attributes, or null when no address was given
     *
     * @return array<string, mixed>|null
     */
    public function addressAttributes(): ?array
    {
        /** @var array<string, mixed>|null $address */
        $address = $this->validated('address');

        return $address === null ? null : collect($address)->only(Address::FIELDS)->toArray();
    }
}
