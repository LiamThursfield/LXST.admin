<?php

namespace App\Data\Crm;

use Spatie\LaravelData\Data;

/**
 * The type-specific settings of a CRM form field. Only some settings apply to each type
 * (see FormFieldType::settingKeys()); the rest are always null.
 */
class FormFieldSettings extends Data
{
    public function __construct(
        /** The maximum number of characters (text, textarea) */
        public ?int $max_length = null,
        /** The visible height of the box (textarea) */
        public ?int $rows = null,
        /** The smallest allowed value (number) */
        public int|float|null $min = null,
        /** The largest allowed value (number) */
        public int|float|null $max = null,
    ) {}
}
