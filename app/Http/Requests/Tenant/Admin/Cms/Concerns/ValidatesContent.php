<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Cms\ContentRules;
use App\Services\Cms\RichText;

/**
 * For requests that save content for a template's fields (layouts, and pages in #51). The
 * request must say which template it's for, via contentTemplate().
 */
trait ValidatesContent
{
    protected ?ContentRules $contentRuleBuilder = null;

    /**
     * The template whose fields the content is for, or null if none was chosen (or it's invalid)
     */
    abstract protected function contentTemplate(): ?Template;

    /**
     * @return array<string, list<mixed>>
     */
    protected function contentRules(): array
    {
        $template = $this->contentTemplate();

        return $template === null ? [] : $this->contentRuleBuilder()->rules($template->loadMissing('fields'), 'content', $this->all());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $template = $this->contentTemplate();

        return $template === null ? [] : $this->contentRuleBuilder()->attributes($template->loadMissing('fields'));
    }

    /**
     * The validated content, keyed by field slug, in the shape it's stored in
     *
     * @return array<string, mixed>
     */
    public function contentValues(): array
    {
        $template = $this->contentTemplate();

        if ($template === null) {
            return [];
        }

        /** @var array<string, mixed> $content */
        $content = $this->validated('content') ?? [];

        return $template->loadMissing('fields')->fields
            ->mapWithKeys(function (TemplateField $field) use ($content) {
                $value = $content[$field->slug] ?? null;

                return [$field->slug => $this->storedValue($field, $value)];
            })
            ->all();
    }

    /**
     * Numbers as numbers; rich text as its JSON plus HTML built from it; images as their file and
     * alt text. Anything blank becomes null, so no content is saved for it.
     */
    protected function storedValue(TemplateField $field, mixed $value): mixed
    {
        return match ($field->type) {
            TemplateFieldType::Number => is_numeric($value) ? $value + 0 : null,
            TemplateFieldType::Wysiwyg => $this->richTextValue($value),
            TemplateFieldType::Image => is_array($value) && filled($value['file_id'] ?? null)
                ? ['file_id' => (int) $value['file_id'], 'alt' => filled($value['alt'] ?? null) ? (string) $value['alt'] : null]
                : null,
            TemplateFieldType::CmsMenu, TemplateFieldType::CrmForm, TemplateFieldType::CrmOrganisationUnit => is_numeric($value) ? (int) $value : null,
            TemplateFieldType::Component => $this->componentValue($field, $value),
            TemplateFieldType::Repeater => $this->repeaterValue($field, $value),
            default => $value,
        };
    }

    /**
     * The component's fields, keyed by slug, or null if none are filled in
     *
     * @return array<string, mixed>|null
     */
    protected function componentValue(TemplateField $field, mixed $value): ?array
    {
        $template = $this->contentRuleBuilder()->nestedTemplate($field);

        if ($template === null || ! is_array($value)) {
            return null;
        }

        $values = $this->nestedValues($template, $value);

        return $this->contentRuleBuilder()->hasValues($values) ? $values : null;
    }

    /**
     * The rows in order, each keyed by the repeater template's field slugs, or null for no rows
     *
     * @return list<array<string, mixed>>|null
     */
    protected function repeaterValue(TemplateField $field, mixed $value): ?array
    {
        $template = $this->contentRuleBuilder()->nestedTemplate($field);

        if ($template === null || ! is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_map(
            fn (mixed $row) => $this->nestedValues($template, is_array($row) ? $row : []),
            $value,
        ));
    }

    /**
     * Only the template's fields, in their stored shape (anything else sent, e.g. the editor's
     * row keys, is dropped)
     *
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    protected function nestedValues(Template $template, array $value): array
    {
        return $template->fields
            ->mapWithKeys(fn (TemplateField $nested) => [$nested->slug => $this->storedValue($nested, $value[$nested->slug] ?? null)])
            ->all();
    }

    /**
     * One rule builder per request, so nested templates are only loaded once
     */
    protected function contentRuleBuilder(): ContentRules
    {
        return $this->contentRuleBuilder ??= app(ContentRules::class);
    }

    /**
     * @return array{json: array<string, mixed>, html: string}|null
     */
    protected function richTextValue(mixed $value): ?array
    {
        $document = is_array($value) ? ($value['json'] ?? null) : null;
        $richText = app(RichText::class);

        if (! is_array($document) || $richText->isEmpty($document)) {
            return null;
        }

        return ['json' => $document, 'html' => $richText->toHtml($document)];
    }
}
