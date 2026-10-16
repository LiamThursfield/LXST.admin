<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Cms\ContentRules;

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
     * The validated content, keyed by field slug, with numbers stored as numbers
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

                if ($field->type === TemplateFieldType::Number && is_numeric($value)) {
                    $value += 0;
                }

                return [$field->slug => $value];
            })
            ->all();
    }
}
