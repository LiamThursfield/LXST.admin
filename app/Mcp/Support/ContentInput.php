<?php

namespace App\Mcp\Support;

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Cms\ContentRules;
use App\Services\Cms\RichText;

/**
 * Lets the assistant send rich text as HTML: a rich text value that's a string, or `{"html": …}`
 * without `json`, becomes the editor's JSON, at any depth (in components and repeater rows too).
 * Everything else is left for the admin's validation to check.
 */
class ContentInput
{
    public function __construct(
        protected RichText $richText,
        protected ContentRules $rules,
    ) {}

    /**
     * For content sent for the template with the given id, if it's of the given type (anything
     * else is left for validation to refuse)
     *
     * @param  array<string, mixed>  $content  Keyed by field slug
     * @return array<string, mixed>
     */
    public function forTemplate(TemplateType $type, mixed $templateId, array $content): array
    {
        $template = is_numeric($templateId)
            ? Template::query()->where('type', $type)->whereKey((int) $templateId)->first()
            : null;

        return $template === null ? $content : $this->normalise($template, $content);
    }

    /**
     * @param  array<string, mixed>  $content  Keyed by field slug
     * @return array<string, mixed>
     */
    public function normalise(Template $template, array $content): array
    {
        foreach ($template->loadMissing('fields')->fields as $field) {
            if (array_key_exists($field->slug, $content)) {
                $content[$field->slug] = $this->value($field, $content[$field->slug]);
            }
        }

        return $content;
    }

    protected function value(TemplateField $field, mixed $value): mixed
    {
        return match ($field->type) {
            TemplateFieldType::Wysiwyg => $this->richText($value),
            TemplateFieldType::Component => is_array($value) ? $this->nested($field, $value) : $value,
            TemplateFieldType::Repeater => is_array($value)
                ? array_map(fn (mixed $row) => is_array($row) ? $this->nested($field, $row) : $row, $value)
                : $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected function nested(TemplateField $field, array $value): array
    {
        $template = $this->rules->nestedTemplate($field);

        return $template === null ? $value : $this->normalise($template, $value);
    }

    protected function richText(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : ['json' => $this->richText->fromHtml($value)];
        }

        if (is_array($value) && ! isset($value['json']) && is_string($value['html'] ?? null)) {
            return ['json' => $this->richText->fromHtml($value['html'])];
        }

        return $value;
    }
}
