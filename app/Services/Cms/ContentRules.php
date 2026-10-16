<?php

namespace App\Services\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;

/**
 * Builds the validation rules for content (keyed by field slug, under `content.`) from a
 * template's fields, respecting each field's required flag and settings
 */
class ContentRules
{
    /**
     * Text fields with no maximum set still fit in a normal string column's worth
     */
    public const int DEFAULT_TEXT_MAX_LENGTH = 255;

    /**
     * Text areas with no maximum set are kept to a sensible size
     */
    public const int DEFAULT_TEXTAREA_MAX_LENGTH = 65535;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(Template $template, string $prefix = 'content'): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($template->fields as $field) {
            $rules["{$prefix}.{$field->slug}"] = [
                $field->is_required ? 'required' : 'nullable',
                ...$this->typeRules($field),
            ];
        }

        return $rules;
    }

    /**
     * The fields' names, so messages say e.g. "The Hero title field is required"
     *
     * @return array<string, string>
     */
    public function attributes(Template $template, string $prefix = 'content'): array
    {
        return $template->fields
            ->mapWithKeys(fn (TemplateField $field) => ["{$prefix}.{$field->slug}" => $field->name])
            ->all();
    }

    /**
     * @return list<mixed>
     */
    protected function typeRules(TemplateField $field): array
    {
        $settings = $field->settings;

        return match ($field->type) {
            TemplateFieldType::Text => ['string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXT_MAX_LENGTH)],
            TemplateFieldType::Textarea => ['string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXTAREA_MAX_LENGTH)],
            TemplateFieldType::Number => array_values(array_filter([
                'numeric',
                $settings?->min !== null ? 'min:'.$settings->min : null,
                $settings?->max !== null ? 'max:'.$settings->max : null,
            ])),
            // Editors for the other types arrive in #49–#53; until then their values are kept as sent
            default => [],
        };
    }
}
