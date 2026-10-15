<?php

namespace App\Enums\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasLabels
{
    /**
     * @return array<array{
     *  value: string,
     *  label: string
     * }>
     */
    public static function asSelectItems(): array
    {
        return Arr::map(self::cases(), fn (self $case) => $case->toSelectItem());
    }

    /**
     * Returns the translated label from lang/{locale}/enums.php, keyed by the snake case enum name and value
     * e.g. enums.task_definition_status.active
     *
     * Falls back to the value as title case, replacing hyphens and underscores with spaces.
     */
    public function label(): string
    {
        // If the Enum defines its own getCustomLabel method, use it
        /** @phpstan-ignore-next-line  */ // ignored as no enum implements this currently
        if (method_exists($this, 'getCustomLabel')) {
            return $this->getCustomLabel();
        }

        $translationKey = static::labelTranslationKey($this->value);

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return (string) Str::of($this->value)
            ->replace(['-', '_'], ' ')
            ->title();
    }

    /**
     * Converts the value to a keyed array consisting of the value and the label,
     * useful for frontend selects
     *
     * @return array{
     *     value: string,
     *     label: string,
     * }
     */
    public function toSelectItem(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * Returns the translation key for a value's label, e.g. enums.task_definition_status.active
     */
    public static function labelTranslationKey(string $value): string
    {
        return 'enums.'.Str::snake(class_basename(static::class)).'.'.$value;
    }
}
