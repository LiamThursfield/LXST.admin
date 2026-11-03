<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\TemplateType;
use App\Http\Requests\Tenant\Admin\Cms\ImportedSavedComponentData;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
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
        return is_string($item['slug'] ?? null) ? "{$this->kind()}/{$item['slug']}" : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function dependencies(array $item): array
    {
        $reference = $item['template'] ?? null;

        return is_array($reference) && is_string($reference['type'] ?? null) && is_string($reference['slug'] ?? null)
            ? ["{$reference['type']}/{$reference['slug']}"]
            : [];
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
        $template = $this->template($item['template'] ?? null);

        if ($template === null) {
            return ['errors' => [__('cms.import.validation.missing_saved_component_template', [
                'template' => $this->dependencies($item)[0] ?? '?',
            ])], 'warnings' => []];
        }

        /** @var list<MissingReference> $missing */
        $missing = [];
        $content = is_array($item['content'] ?? null) ? $item['content'] : [];
        $values = $this->values->import($template, $content, $missing);

        $errors = [];
        $warnings = [];

        foreach ($missing as $reference) {
            if ($reference['required']) {
                $errors[] = $this->missingMessage('required', $reference);
            } else {
                $warnings[] = $this->missingMessage('optional', $reference);
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $data = ImportedSavedComponentData::for([
            'template_id' => $template->id,
            'name' => $item['name'] ?? null,
            'slug' => $item['slug'],
            'content' => $values,
        ], $existing);

        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $savedComponent = $existing ?? SavedComponent::create($data->savedComponentAttributes());
        $existing?->update($data->savedComponentAttributes());
        $savedComponent->syncContent($data->contentValues());

        return ['errors' => [], 'warnings' => $warnings];
    }

    protected function template(mixed $reference): ?Template
    {
        $type = is_array($reference) && is_string($reference['type'] ?? null) ? TemplateType::tryFrom($reference['type']) : null;

        return $type !== null && is_string($reference['slug'] ?? null)
            ? Template::query()->with('fields')->where('type', $type)->where('slug', $reference['slug'])->first()
            : null;
    }

    /**
     * @param  MissingReference  $reference
     */
    protected function missingMessage(string $severity, array $reference): string
    {
        return __("cms.import.missing.{$severity}", [
            'what' => __("cms.import.missing.kinds.{$reference['kind']}", ['reference' => $reference['reference']]),
            'field' => $reference['field'],
        ]);
    }
}
