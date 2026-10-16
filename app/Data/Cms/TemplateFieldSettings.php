<?php

namespace App\Data\Cms;

use Spatie\LaravelData\Data;

/**
 * The type-specific settings of a CMS template field. Only some settings apply to each type
 * (see TemplateFieldType::settingKeys()); the rest are always null.
 */
class TemplateFieldSettings extends Data
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
        /** The template whose fields this field holds (component, repeater) */
        public ?int $template_id = null,
        /** The fewest rows (repeater) */
        public ?int $min_items = null,
        /** The most rows (repeater) */
        public ?int $max_items = null,
    ) {}
}
