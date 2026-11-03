<?php

namespace App\Services\Cms\Portable;

use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;

/**
 * Turns saved components into portable items, with their templates included and placed first
 */
class SavedComponentExporter
{
    public function __construct(
        protected PortableContent $portable,
        protected TemplateExporter $templates,
        protected PortableValues $values,
    ) {}

    /**
     * @param  iterable<SavedComponent>  $savedComponents
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function export(iterable $savedComponents): array
    {
        $templates = [];
        $items = [];

        foreach ($savedComponents as $savedComponent) {
            $templates[] = $savedComponent->template;
            $items[] = $this->item($savedComponent);
        }

        return $this->portable->wrap([...$this->templates->items($templates), ...$items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function item(SavedComponent $savedComponent): array
    {
        /** @var Template $template */
        $template = $savedComponent->template()->with('fields')->firstOrFail();

        return [
            'kind' => 'saved_component',
            'slug' => $savedComponent->slug,
            'name' => $savedComponent->name,
            'template' => ['type' => $template->type->value, 'slug' => $template->slug],
            'content' => $this->values->export($template, $savedComponent->contentValues()),
        ];
    }
}
