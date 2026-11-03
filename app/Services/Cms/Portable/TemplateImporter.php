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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports templates from portable JSON. A dry run does the real import in a transaction that's always
 * rolled back, so what it reports is exactly what confirming would do, including the checks that
 * depend on templates earlier in the same file.
 *
 * @phpstan-type ImportResult array{key: string, kind: string, type: string, type_label: string, slug: string, name: string, action: string, can_overwrite: bool, changes: list<string>, warnings: list<string>, errors: list<string>}
 */
class TemplateImporter
{
    use SyncsBuilderFields;
    use SyncsBuilderSections;

    public const string ACTION_CREATE = 'create';

    public const string ACTION_OVERWRITE = 'overwrite';

    public const string ACTION_SKIP = 'skip';

    public const string ACTION_UNCHANGED = 'unchanged';

    public function __construct(
        protected PortableContent $portable,
        protected TemplateExporter $exporter,
    ) {}

    /**
     * What importing would do, saving nothing
     *
     * @param  list<string>  $overwrite  The keys of the items to overwrite rather than skip
     * @return list<ImportResult>
     *
     * @throws ValidationException
     */
    public function preview(string $json, array $overwrite = []): array
    {
        $items = $this->validItems($json);

        DB::beginTransaction();

        try {
            return $this->run($items, $overwrite);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Imports everything, or nothing if any of it fails
     *
     * @param  list<string>  $overwrite  The keys of the items to overwrite rather than skip
     * @return list<ImportResult>
     *
     * @throws ValidationException
     */
    public function import(string $json, array $overwrite = []): array
    {
        $items = $this->validItems($json);

        return DB::transaction(function () use ($items, $overwrite) {
            $results = $this->run($items, $overwrite);

            foreach ($results as $result) {
                if ($result['errors'] !== []) {
                    throw $this->portable->invalid(__('cms.import.validation.item_failed', ['name' => $result['name'], 'error' => $result['errors'][0]]));
                }
            }

            return $results;
        });
    }

    /**
     * Checks the items are templates this version understands, each once. Their fields are checked
     * later, by the same rules as the template builder.
     *
     * @return array<string, array<string, mixed>> By key
     *
     * @throws ValidationException
     */
    protected function validItems(string $json): array
    {
        $items = [];

        foreach ($this->portable->items($json) as $position => $item) {
            $type = is_array($item) && is_string($item['type'] ?? null) ? TemplateType::tryFrom($item['type']) : null;

            if ($type === null || ($item['kind'] ?? null) !== 'template' || ! is_string($item['slug'] ?? null)) {
                throw $this->portable->invalid(__('cms.import.validation.unknown_item', ['number' => $position + 1]));
            }

            $key = $this->key($type->value, $item['slug']);

            if (isset($items[$key])) {
                throw $this->portable->invalid(__('cms.import.validation.duplicate_item', ['slug' => $item['slug']]));
            }

            /** @var array<string, mixed> $item */
            $items[$key] = $item;
        }

        return $items;
    }

    protected function key(string $type, string $slug): string
    {
        return "{$type}/{$slug}";
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<string>  $overwrite
     * @return list<ImportResult>
     */
    protected function run(array $items, array $overwrite): array
    {
        $loops = [];
        $ordered = [];

        foreach (array_keys($items) as $key) {
            $this->addInDependencyOrder($key, $items, $ordered, $loops);
        }

        return array_map(
            fn (string $key) => $this->importItem($key, $items[$key], in_array($key, $overwrite, true), isset($loops[$key])),
            array_keys($ordered),
        );
    }

    /**
     * Dependencies go first, whatever order the file has them in, so they exist by the time they're
     * needed. Items that nest each other are noted, as no order would work.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, true>  $ordered
     * @param  array<string, true>  $loops
     * @param  list<string>  $path
     */
    protected function addInDependencyOrder(string $key, array $items, array &$ordered, array &$loops, array $path = []): void
    {
        if (isset($ordered[$key])) {
            return;
        }

        if (in_array($key, $path, true)) {
            foreach (array_slice($path, (int) array_search($key, $path, true)) as $inLoop) {
                $loops[$inLoop] = true;
            }

            return;
        }

        foreach ($this->fieldsOf($items[$key]) as $field) {
            $reference = $this->referenceOf($field);

            if ($reference !== null && isset($items[$reference])) {
                $this->addInDependencyOrder($reference, $items, $ordered, $loops, [...$path, $key]);
            }
        }

        $ordered[$key] = true;
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
            ? $this->key($reference['type'], $reference['slug'])
            : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return ImportResult
     */
    protected function importItem(string $key, array $item, bool $overwrite, bool $isInLoop): array
    {
        $type = TemplateType::from($item['type']);
        $existing = Template::query()->where('type', $type)->where('slug', $item['slug'])->first();

        $isUnchanged = $existing !== null && $this->canonical($this->exporter->item($existing)) === $this->canonical($item);
        $canOverwrite = $existing !== null && ! $isUnchanged;

        $result = [
            'key' => $key,
            'kind' => 'template',
            'type' => $type->value,
            'type_label' => $type->label(),
            'slug' => $item['slug'],
            'name' => is_string($item['name'] ?? null) ? $item['name'] : $item['slug'],
            'action' => match (true) {
                $existing === null => self::ACTION_CREATE,
                $isUnchanged => self::ACTION_UNCHANGED,
                $overwrite => self::ACTION_OVERWRITE,
                default => self::ACTION_SKIP,
            },
            'can_overwrite' => $canOverwrite,
            'changes' => $canOverwrite ? $this->changes($existing, $item) : [],
            'warnings' => $canOverwrite ? $this->warnings($existing, $item) : [],
            'errors' => [],
        ];

        if (! in_array($result['action'], [self::ACTION_CREATE, self::ACTION_OVERWRITE], true)) {
            return $result;
        }

        $result['errors'] = $isInLoop ? [__('cms.templates.validation.nests_itself')] : $this->missingTemplates($item);

        if ($result['errors'] === []) {
            $result['errors'] = $this->save($item, $existing);
        }

        return $result;
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
     * @return list<string> Anything stopping it being saved
     */
    protected function save(array $item, ?Template $existing): array
    {
        $data = ImportedTemplateData::for($this->builderData($item, $existing), $existing);

        try {
            $data->validateResolved();
        } catch (ValidationException $exception) {
            return array_values(array_unique(array_merge(...array_values($exception->errors()))));
        }

        $template = $existing ?? Template::create($data->templateAttributes());
        $existing?->update($data->templateAttributes());

        $sectionIds = $this->syncBuilderSections($template->sections(), $data->sectionsAttributes());
        $this->syncBuilderFields($template->fields(), $data->fieldsAttributes($sectionIds));

        return [];
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
     * A form of an item that can be compared, whichever environment it's from and however it's laid out
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function canonical(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            $item = is_array($item) ? $this->canonical($item) : $item;

            if ($item !== null && $item !== [] && $item !== false && $item !== '') {
                $canonical[$key] = $item;
            }
        }

        if (! array_is_list($value)) {
            ksort($canonical);
        }

        return $canonical;
    }

    /**
     * What overwriting would do to the template's fields
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function changes(Template $existing, array $item): array
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
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function warnings(Template $existing, array $item): array
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
