<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * The kinds of organisation unit, from the top of the hierarchy down:
 * a company has locations, and a location has departments (which may have sub-departments)
 */
enum OrganisationUnitType: string
{
    use HasLabels;

    case Company = 'company';
    case Location = 'location';
    case Department = 'department';

    /**
     * The types a unit of this type may have as its parent
     *
     * @return OrganisationUnitType[]
     */
    public function allowedParentTypes(): array
    {
        return match ($this) {
            self::Company => [self::Company],
            self::Location => [self::Company],
            self::Department => [self::Location, self::Department],
        };
    }

    /**
     * Whether a unit of this type must have a parent
     */
    public function requiresParent(): bool
    {
        return $this !== self::Company;
    }
}
