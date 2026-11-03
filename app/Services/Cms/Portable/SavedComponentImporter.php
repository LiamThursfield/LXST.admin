<?php

namespace App\Services\Cms\Portable;

use App\Http\Requests\Tenant\Admin\Cms\ImportedSavedComponentData;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use Illuminate\Database\Eloquent\Model;

/**
 * Imports saved components, by their slug
 *
 * @extends ItemImporter<SavedComponent>
 *
 * @phpstan-import-type MissingReference from PortableValues
 */
class SavedComponentImporter extends ItemImporter
{
    use ChecksImportedData;

    public function __construct(
        protected SavedComponentExporter $exporter,
        protected PortableValues $values,
    ) {}

    public function kind(): string
    {
        return 'saved_component';
    }

    /**
     * @param  array<mixed>  $item
     */
    public function key(array $item): ?string
    {
        return $this->validSlug($item['slug'] ?? null) ? "{$this->kind()}/{$item['slug']}" : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function dependencies(array $item): array
    {
        $template = $this->templateKey($item['template'] ?? null);

        return $template === null ? [] : [$template];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function typeLabel(array $item): string
    {
        return __('cms.import.kinds.saved_component');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?SavedComponent
    {
        return SavedComponent::query()->where('slug', $item['slug'])->first();
    }

    /**
     * @param  SavedComponent  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->item($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', SavedComponent::class) ?? false;
    }

    /**
     * Edits to a saved component go live everywhere it's used
     *
     * @param  SavedComponent  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        $owners = $existing->usedBy();

        if ($owners->isEmpty()) {
            return [];
        }

        $counts = array_values(array_filter([
            $this->places('pages', $owners->whereInstanceOf(Page::class)->count()),
            $this->places('layouts', $owners->whereInstanceOf(Layout::class)->count()),
        ]));

        return [__('cms.import.warnings.saved_component_in_use', ['places' => implode(', ', $counts)])];
    }

    protected function places(string $kind, int $count): ?string
    {
        return $count === 0 ? null : trans_choice("cms.import.places.{$kind}", $count);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  SavedComponent|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $template = $this->referencedTemplate($item['template'] ?? null);

        if ($template === null) {
            return ['errors' => [$this->missingTemplateMessage($item['template'] ?? null)], 'warnings' => []];
        }

        if ($existing === null) {
            SavedComponent::create(['template_id' => $template->id, 'name' => $this->placeholderName($item), 'slug' => $item['slug']]);
        }

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function fill(array $item): array
    {
        $savedComponent = $this->existing($item);
        $template = $this->referencedTemplate($item['template'] ?? null);

        if ($savedComponent === null || $template === null) {
            return ['errors' => [], 'warnings' => []];
        }

        /** @var list<MissingReference> $missing */
        $missing = [];
        $values = $this->values->import($template, is_array($item['content'] ?? null) ? $item['content'] : [], $missing);
        $outcome = $this->missingOutcome($missing);

        if ($outcome['errors'] !== []) {
            return $outcome;
        }

        $data = ImportedSavedComponentData::for([
            'template_id' => $template->id,
            'name' => $item['name'] ?? null,
            'slug' => $item['slug'],
            'content' => $values,
        ], $savedComponent);

        $outcome['errors'] = $this->validationErrors($data);

        if ($outcome['errors'] === []) {
            $savedComponent->update($data->savedComponentAttributes());
            $savedComponent->syncContent($data->contentValues());
        }

        return $outcome;
    }
}
