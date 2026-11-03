<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use Illuminate\Support\Collection;

/**
 * Turns templates into portable items, with the templates they nest included and placed first
 */
class TemplateExporter
{
    public function __construct(protected PortableContent $portable) {}

    /**
     * @param  iterable<Template>  $templates
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function export(iterable $templates): array
    {
        return $this->portable->wrap($this->items($templates));
    }

    /**
     * The templates and those they nest, each once, with dependencies first
     *
     * @param  iterable<Template>  $templates
     * @return list<array<string, mixed>>
     */
    public function items(iterable $templates): array
    {
        $ordered = [];

        foreach ($templates as $template) {
            $this->addWithDependencies($template, $ordered);
        }

        return array_values(array_map($this->item(...), $ordered));
    }

    /**
     * @param  array<int, Template>  $ordered
     * @param  list<int>  $path  The templates being added, so a loop can't recurse forever
     */
    protected function addWithDependencies(Template $template, array &$ordered, array $path = []): void
    {
        if (isset($ordered[$template->id]) || in_array($template->id, $path, true)) {
            return;
        }

        foreach ($template->nestedTemplates() as $nested) {
            $this->addWithDependencies($nested, $ordered, [...$path, $template->id]);
        }

        $ordered[$template->id] = $template;
    }

    /**
     * @return array<string, mixed>
     */
    public function item(Template $template): array
    {
        $template->loadMissing(['fields', 'sections']);

        /** @var Collection<int, Template> $nested */
        $nested = $template->nestedTemplates()->keyBy('id');
        $inSection = fn (?int $sectionId) => $template->fields
            ->filter(fn (TemplateField $field) => $field->section_id === $sectionId)
            ->map(fn (TemplateField $field) => $this->field($field, $nested))
            ->values()
            ->all();

        return [
            'kind' => 'template',
            'type' => $template->type->value,
            'slug' => $template->slug,
            'name' => $template->name,
            'description' => $template->description,
            'fields' => $inSection(null),
            'sections' => $template->sections->map(fn ($section) => [
                'name' => $section->name,
                'description' => $section->description,
                'is_collapsed' => $section->is_collapsed,
                'fields' => $inSection($section->id),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Template>  $nested
     * @return array<string, mixed>
     */
    protected function field(TemplateField $field, Collection $nested): array
    {
        return [
            'slug' => $field->slug,
            'type' => $field->type->value,
            'name' => $field->name,
            'description' => $field->description,
            'is_required' => $field->is_required,
            'settings' => $this->settings($field, $nested),
        ];
    }

    /**
     * Each type's settings, with no default case so a new field type can't be forgotten. A nested
     * template is referred to by its type and slug, as its id differs between environments.
     *
     * @param  Collection<int, Template>  $nested
     * @return array<string, mixed>
     */
    protected function settings(TemplateField $field, Collection $nested): array
    {
        $settings = $field->settings;
        $template = $nested->get($settings?->template_id);
        $reference = $template === null ? [] : ['template' => ['type' => $template->type->value, 'slug' => $template->slug]];

        $portable = match ($field->type) {
            TemplateFieldType::Text => ['max_length' => $settings?->max_length],
            TemplateFieldType::Textarea => ['max_length' => $settings?->max_length, 'rows' => $settings?->rows],
            TemplateFieldType::Number => ['min' => $settings?->min, 'max' => $settings?->max],
            TemplateFieldType::Select => ['options' => $settings?->options, 'default' => $settings?->default],
            TemplateFieldType::Component => $reference,
            TemplateFieldType::Repeater => [...$reference, 'min_items' => $settings?->min_items, 'max_items' => $settings?->max_items],
            TemplateFieldType::CrmOrganisationUnit => ['organisation_unit_type' => $settings?->organisation_unit_type],
            TemplateFieldType::Wysiwyg,
            TemplateFieldType::Image,
            TemplateFieldType::Link,
            TemplateFieldType::CmsMenu,
            TemplateFieldType::CrmForm => [],
        };

        return array_filter(
            [...$portable, 'width' => $settings?->width?->value],
            fn (mixed $value) => $value !== null,
        );
    }
}
