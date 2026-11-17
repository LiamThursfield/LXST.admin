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
        /**
         * The choices, in order. Values are stable strings the site submits (select)
         *
         * @var list<array{label: string, value: string}>|null
         */
        public ?array $options = null,
        /** The option chosen to begin with (select) */
        public ?string $default = null,
    ) {}

    /**
     * The label of the option with this value, or null when there's no such option
     */
    public function optionLabel(mixed $value): ?string
    {
        foreach ($this->options ?? [] as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * The options' values, in order
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_column($this->options ?? [], 'value');
    }
}
