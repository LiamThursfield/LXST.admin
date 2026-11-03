<?php

namespace App\Data\Cms;

use App\Enums\Cms\TemplateFieldWidth;
use Spatie\LaravelData\Data;

/**
 * The settings of a CMS template field. Only some settings apply to each type (see
 * TemplateFieldType::settingKeys()); the rest are always null. The width applies to every type.
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
        /**
         * The choices, in order. Values are stable strings the site receives (select)
         *
         * @var list<array{label: string, value: string}>|null
         */
        public ?array $options = null,
        /** The value used when nothing is chosen. It's never stored with the content, so changing it updates existing content (select) */
        public ?string $default = null,
        /** The template whose fields this field holds (component, repeater) */
        public ?int $template_id = null,
        /** The fewest rows (repeater) */
        public ?int $min_items = null,
        /** The most rows (repeater) */
        public ?int $max_items = null,
        /** Only offer organisation units of this type, e.g. "location" (crm_organisation_unit) */
        public ?string $organisation_unit_type = null,
        /** How wide it is in the content editor; null is full width (every type) */
        public ?TemplateFieldWidth $width = null,
    ) {}
}
