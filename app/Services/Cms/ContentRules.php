<?php

namespace App\Services\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\FileManager\File;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

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

    public function __construct(protected RichText $richText) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(Template $template, string $prefix = 'content'): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($template->fields as $field) {
            $rules = [...$rules, ...$this->fieldRules($field, "{$prefix}.{$field->slug}")];
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
            ->flatMap(fn (TemplateField $field) => [
                "{$prefix}.{$field->slug}" => $field->name,
                // Image fields are validated by their file and alt text
                "{$prefix}.{$field->slug}.file_id" => $field->name,
                "{$prefix}.{$field->slug}.alt" => __('cms.content.image.alt_attribute', ['field' => $field->name]),
            ])
            ->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function fieldRules(TemplateField $field, string $key): array
    {
        $required = $field->is_required ? 'required' : 'nullable';
        $settings = $field->settings;

        return match ($field->type) {
            TemplateFieldType::Text => [$key => [$required, 'string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXT_MAX_LENGTH)]],
            TemplateFieldType::Textarea => [$key => [$required, 'string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXTAREA_MAX_LENGTH)]],
            TemplateFieldType::Number => [$key => array_values(array_filter([
                $required,
                'numeric',
                $settings?->min !== null ? 'min:'.$settings->min : null,
                $settings?->max !== null ? 'max:'.$settings->max : null,
            ]))],
            // Sent as {json, html}; only the JSON is used, and the HTML is rebuilt from it
            TemplateFieldType::Wysiwyg => [$key => [$required, 'array', $this->richTextRule($field)]],
            // Sent as {file_id, alt}
            TemplateFieldType::Image => [
                $key => ['nullable', 'array'],
                "{$key}.file_id" => [
                    $required,
                    'integer',
                    Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%')),
                ],
                "{$key}.alt" => ['nullable', 'string', 'max:255'],
            ],
            // Editors for the other types arrive in #50–#53; until then their values are kept as sent
            default => [$key => ['nullable']],
        };
    }

    /**
     * The document must be one the editor could have produced, with safe URLs, and not be blank
     * when the field is required
     */
    protected function richTextRule(TemplateField $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $document = is_array($value) ? ($value['json'] ?? null) : null;

            foreach ($this->richText->problems($document) as $problem) {
                $fail($problem);
            }

            if ($field->is_required && is_array($document) && $this->richText->isEmpty($document)) {
                $fail(__('validation.required', ['attribute' => $field->name]));
            }
        };
    }
}
