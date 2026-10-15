<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * The types of field a CRM form can have. The `crm_*` types are mapped onto
 * the submitting contact's details (e.g. crm_email fills in Contact::$email).
 */
enum FormFieldType: string
{
    use HasLabels;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Hidden = 'hidden';

    case ContactEmail = 'crm_email';
    case ContactTitle = 'crm_title';
    case ContactFirstName = 'crm_first_name';
    case ContactLastName = 'crm_last_name';
    case ContactTelephone = 'crm_telephone';

    /**
     * The contact attribute this field fills in, or null for a standard field
     */
    public function contactAttribute(): ?string
    {
        return match ($this) {
            self::ContactEmail => 'email',
            self::ContactTitle => 'title',
            self::ContactFirstName => 'first_name',
            self::ContactLastName => 'last_name',
            self::ContactTelephone => 'telephone',
            default => null,
        };
    }

    /**
     * Whether the field fills in one of the contact's details
     */
    public function isContactField(): bool
    {
        return $this->contactAttribute() !== null;
    }

    /**
     * The FormFieldSettings that apply to this type
     *
     * @return list<string>
     */
    public function settingKeys(): array
    {
        return match ($this) {
            self::Text => ['max_length'],
            self::Textarea => ['max_length', 'rows'],
            self::Number => ['min', 'max'],
            default => [],
        };
    }
}
