<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * The kinds of organisation unit, from the top of the hierarchy down:
 * a company has locations, and a location has departments. Companies are always at the top.
 */
enum OrganisationUnitType: string
{
    use HasLabels;

    case Company = 'company';
    case Location = 'location';
    case Department = 'department';

    /**
     * The types a unit of this type may have as its parent (none for a company, which is always at the top)
     *
     * @return OrganisationUnitType[]
     */
    public function allowedParentTypes(): array
    {
        return match ($this) {
            self::Company => [],
            self::Location => [self::Company],
            self::Department => [self::Location],
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
