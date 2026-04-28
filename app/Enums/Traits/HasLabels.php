<?php

namespace App\Enums\Traits;

use Illuminate\Support\Arr;
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
     * Converts the value to a title case label, replacing hyphens with spaces.
     */
    public function label(): string
    {
        // If the Enum defines its own getCustomLabel method, use it
        /** @phpstan-ignore-next-line  */ // ignored as no enum implements this currently
        if (method_exists($this, 'getCustomLabel')) {
            return $this->getCustomLabel();
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
}
