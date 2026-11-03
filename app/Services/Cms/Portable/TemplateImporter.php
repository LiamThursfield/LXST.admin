<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\TemplateType;
use App\Http\Controllers\Concerns\SyncsBuilderFields;
use App\Http\Controllers\Concerns\SyncsBuilderSections;
use App\Http\Requests\Tenant\Admin\Cms\ImportedTemplateData;
use App\Models\Cms\Content;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Imports templates, by their type and slug. A nested template is referred to the same way.
 *
 * @extends ItemImporter<Template>
 */
class TemplateImporter extends ItemImporter
{
    use ChecksImportedData;
    use SyncsBuilderFields;
    use SyncsBuilderSections;

    public function __construct(protected TemplateExporter $exporter) {}

    public function kind(): string
    {
        return 'template';
    }

    /**
     * @param  array<mixed>  $item
     */
    public function key(array $item): ?string
    {
        $type = is_string($item['type'] ?? null) ? TemplateType::tryFrom($item['type']) : null;

        return $type !== null && is_string($item['slug'] ?? null) ? $this->referenceKey($type->value, $item['slug']) : null;
    }

    protected function referenceKey(string $type, string $slug): string
    {
        return "{$type}/{$slug}";
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function dependencies(array $item): array
    {
        return array_values(array_filter(array_map($this->referenceOf(...), $this->fieldsOf($item))));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function type(array $item): string
    {
        return $item['type'];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function typeLabel(array $item): string
    {
        return TemplateType::from($item['type'])->label();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Template
    {
        return Template::query()->where('type', TemplateType::from($item['type']))->where('slug', $item['slug'])->first();
    }

    /**
     * @param  Template  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->item($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Template::class) ?? false;
    }

    /**
     * The item's fields, wherever they are
     *
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    protected function fieldsOf(array $item): array
    {
        $lists = [$item['fields'] ?? []];

        foreach (is_array($item['sections'] ?? null) ? $item['sections'] : [] as $section) {
            $lists[] = is_array($section) ? ($section['fields'] ?? []) : [];
        }

        $fields = [];

        foreach ($lists as $list) {
            foreach (is_array($list) ? $list : [] as $field) {
                if (is_array($field)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * The key of the template a field nests, if it says which
     *
     * @param  array<string, mixed>  $field
     */
    protected function referenceOf(array $field): ?string
    {
        $reference = is_array($field['settings'] ?? null) ? ($field['settings']['template'] ?? null) : null;

        return is_array($reference) && is_string($reference['type'] ?? null) && is_string($reference['slug'] ?? null)
            ? $this->referenceKey($reference['type'], $reference['slug'])
            : null;
    }

    /**
     * Fields nesting a template that's in neither this import nor this site. By this point any in
     * the import have been saved, unless they failed.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function missingTemplates(array $item): array
    {
        $errors = [];

        foreach ($this->fieldsOf($item) as $field) {
            $reference = is_array($field['settings'] ?? null) ? ($field['settings']['template'] ?? null) : null;

            if ($reference !== null && $this->referencedTemplate($reference) === null) {
                $errors[] = __('cms.import.validation.missing_template', [
                    'field' => is_string($field['name'] ?? null) ? $field['name'] : '?',
                    'template' => $this->referenceOf($field) ?? '?',
                ]);
            }
        }

        return $errors;
    }

    protected function referencedTemplate(mixed $reference): ?Template
    {
        $type = is_array($reference) && is_string($reference['type'] ?? null) ? TemplateType::tryFrom($reference['type']) : null;

        return $type !== null && is_string($reference['slug'] ?? null)
            ? Template::query()->where('type', $type)->where('slug', $reference['slug'])->first()
            : null;
    }

    /**
     * Saves the template through the same rules and steps as the template builder
     *
     * @param  array<string, mixed>  $item
     * @param  Template|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $missing = $this->missingTemplates($item);

        if ($missing !== []) {
            return ['errors' => $missing, 'warnings' => []];
        }

        $data = ImportedTemplateData::for($this->builderData($item, $existing), $existing);
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => []];
        }

        $template = $existing ?? Template::create($data->templateAttributes());
        $existing?->update($data->templateAttributes());

        $sectionIds = $this->syncBuilderSections($template->sections(), $data->sectionsAttributes());
        $this->syncBuilderFields($template->fields(), $data->fieldsAttributes($sectionIds));

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * The item as the template builder would send it. Only what's expected is copied, so ids in the
     * paste go no further: existing fields and sections are found by their slug and name, and nested
     * templates by their reference.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function builderData(array $item, ?Template $existing): array
    {
        $fieldIds = $existing?->fields()->pluck('id', 'slug')->all() ?? [];
        $sectionIds = $existing?->sections()->pluck('id', 'name')->all() ?? [];

        $fields = fn (mixed $list) => is_array($list)
            ? array_map(fn (mixed $field) => is_array($field) ? $this->builderField($field, $fieldIds) : $field, array_values($list))
            : $list;

        return [
            'type' => $item['type'],
            'slug' => $item['slug'],
            'name' => $item['name'] ?? null,
            'description' => $item['description'] ?? null,
            'fields' => $fields($item['fields'] ?? []),
            'sections' => is_array($item['sections'] ?? null)
                ? array_map(function (mixed $section) use ($fields, $sectionIds) {
                    if (! is_array($section)) {
                        return $section;
                    }

                    return [
                        'id' => is_string($section['name'] ?? null) ? ($sectionIds[$section['name']] ?? null) : null,
                        'name' => $section['name'] ?? null,
                        'description' => $section['description'] ?? null,
                        'is_collapsed' => $section['is_collapsed'] ?? false,
                        'fields' => $fields($section['fields'] ?? []),
                    ];
                }, array_values($item['sections']))
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, int>  $fieldIds
     * @return array<string, mixed>
     */
    protected function builderField(array $field, array $fieldIds): array
    {
        $settings = $field['settings'] ?? null;

        if (is_array($settings)) {
            $settings['template_id'] = $this->referencedTemplate($settings['template'] ?? null)?->id;
            unset($settings['template']);
        }

        return [
            'id' => is_string($field['slug'] ?? null) ? ($fieldIds[$field['slug']] ?? null) : null,
            'slug' => $field['slug'] ?? null,
            'type' => $field['type'] ?? null,
            'name' => $field['name'] ?? null,
            'description' => $field['description'] ?? null,
            'is_required' => $field['is_required'] ?? false,
            'settings' => $settings,
        ];
    }

    /**
     * What overwriting would do to the template's fields
     *
     * @param  Template  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function changes(Model $existing, array $item): array
    {
        $incoming = $this->incomingTypes($item);
        $current = $existing->fields()->get();

        return array_values(array_filter([
            $this->countChange('added', count(array_diff_key($incoming, $current->pluck('id', 'slug')->all()))),
            $this->countChange('removed', $this->removedFields($current, $incoming)->count()),
            $this->countChange('retyped', $this->retypedFields($current, $incoming)->count()),
        ]));
    }

    protected function countChange(string $change, int $count): ?string
    {
        return $count === 0 ? null : trans_choice("cms.import.changes.{$change}", $count);
    }

    /**
     * What overwriting would lose: a removed field's content is deleted, and a field that changes
     * type may no longer be able to show what's saved
     *
     * @param  Template  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        $incoming = $this->incomingTypes($item);
        $current = $existing->fields()->get();

        return array_values(array_filter([
            $this->lossWarning('removes', $existing, $this->removedFields($current, $incoming)),
            $this->lossWarning('retypes', $existing, $this->retypedFields($current, $incoming)),
        ]));
    }

    /**
     * @param  Collection<int, TemplateField>  $fields
     */
    protected function lossWarning(string $kind, Template $template, Collection $fields): ?string
    {
        if ($fields->isEmpty()) {
            return null;
        }

        $places = $this->placesWithContent($template, $fields);

        return $places === null ? null : trans_choice("cms.import.warnings.{$kind}", $fields->count(), ['places' => $places]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed> Each incoming field's type, by slug
     */
    protected function incomingTypes(array $item): array
    {
        $types = [];

        foreach ($this->fieldsOf($item) as $field) {
            if (is_string($field['slug'] ?? null)) {
                $types[$field['slug']] = $field['type'] ?? null;
            }
        }

        return $types;
    }

    /**
     * @param  Collection<int, TemplateField>  $current
     * @param  array<string, mixed>  $incoming
     * @return Collection<int, TemplateField>
     */
    protected function removedFields(Collection $current, array $incoming): Collection
    {
        return $current->reject(fn (TemplateField $field) => array_key_exists($field->slug, $incoming))->values();
    }

    /**
     * @param  Collection<int, TemplateField>  $current
     * @param  array<string, mixed>  $incoming
     * @return Collection<int, TemplateField>
     */
    protected function retypedFields(Collection $current, array $incoming): Collection
    {
        return $current
            ->filter(fn (TemplateField $field) => array_key_exists($field->slug, $incoming) && $incoming[$field->slug] !== $field->type->value)
            ->values();
    }

    /**
     * Where content could be lost, e.g. "4 pages and 1 layout", or null if nowhere. A page or layout's
     * field has its own content, but a nested template's values sit in the content of the fields that
     * use it, so anything using those counts.
     *
     * @param  Collection<int, TemplateField>  $fields
     */
    protected function placesWithContent(Template $template, Collection $fields): ?string
    {
        $owners = Content::query()
            ->whereIn('template_field_id', [...$fields->pluck('id'), ...$this->fieldsAbove($template)])
            ->get(['contentable_type', 'contentable_id'])
            ->unique(fn (Content $content) => $content->contentable_type.$content->contentable_id)
            ->countBy('contentable_type');

        $places = array_values(array_filter([
            $this->places('pages', $owners->get(Page::class, 0)),
            $this->places('layouts', $owners->get(Layout::class, 0)),
            $this->places('saved_components', $owners->get(SavedComponent::class, 0)),
        ]));

        if ($places === []) {
            return null;
        }

        $last = array_pop($places);

        return $places === [] ? $last : __('cms.import.places.and', ['first' => implode(', ', $places), 'last' => $last]);
    }

    protected function places(string $kind, int $count): ?string
    {
        return $count === 0 ? null : trans_choice("cms.import.places.{$kind}", $count);
    }

    /**
     * The fields that use a template, and those that use the templates they're in
     *
     * @param  list<int>  $visited  Templates already checked, so a loop can't recurse forever
     * @return list<int>
     */
    protected function fieldsAbove(Template $template, array $visited = []): array
    {
        $visited[] = $template->id;
        $ids = [];

        foreach ($template->usedByFields()->with('template')->get() as $field) {
            $ids[] = $field->id;

            if (! in_array($field->template_id, $visited, true)) {
                $ids = [...$ids, ...$this->fieldsAbove($field->template, $visited)];
            }
        }

        return $ids;
    }
}
