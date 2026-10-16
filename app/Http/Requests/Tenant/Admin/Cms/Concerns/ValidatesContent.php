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

        return $template === null ? [] : app(ContentRules::class)->rules($template->loadMissing('fields'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $template = $this->contentTemplate();

        return $template === null ? [] : app(ContentRules::class)->attributes($template->loadMissing('fields'));
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
            default => $value,
        };
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
