<?php

namespace App\Services\Cms\Portable;

use App\Http\Requests\Tenant\Admin\Cms\ImportedLayoutData;
use App\Models\Cms\Layout;
use Illuminate\Database\Eloquent\Model;

/**
 * Imports layouts, by their slug. The record is made first, and its content filled in once everything
 * it could refer to exists, as menus and pages can point back at it.
 *
 * @extends ItemImporter<Layout>
 *
 * @phpstan-import-type MissingReference from PortableValues
 */
class LayoutImporter extends ItemImporter
{
    use ChecksImportedData;

    public function __construct(
        protected SiteExporter $exporter,
        protected PortableValues $values,
    ) {}

    public function kind(): string
    {
        return 'layout';
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
        return __('cms.import.kinds.layout');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Layout
    {
        return Layout::query()->where('slug', $item['slug'])->first();
    }

    /**
     * @param  Layout  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->layoutItem($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Layout::class) ?? false;
    }

    /**
     * @param  Layout  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        $pages = $existing->pages()->count();

        return $pages === 0 ? [] : [(string) __('cms.import.warnings.in_use', ['places' => trans_choice('cms.import.places.pages', $pages)])];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Layout|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $template = $this->referencedTemplate($item['template'] ?? null);

        if ($template === null) {
            return ['errors' => [$this->missingTemplateMessage($item['template'] ?? null)], 'warnings' => []];
        }

        if ($existing === null) {
            Layout::create(['template_id' => $template->id, 'name' => $this->placeholderName($item), 'slug' => $item['slug']]);
        }

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function fill(array $item): array
    {
        $layout = $this->existing($item);
        $template = $this->referencedTemplate($item['template'] ?? null);

        if ($layout === null || $template === null) {
            return ['errors' => [], 'warnings' => []];
        }

        /** @var list<MissingReference> $missing */
        $missing = [];
        $values = $this->values->import($template, is_array($item['content'] ?? null) ? $item['content'] : [], $missing);
        $outcome = $this->missingOutcome($missing);

        if ($outcome['errors'] !== []) {
            return $outcome;
        }

        $data = ImportedLayoutData::for([
            'template_id' => $template->id,
            'name' => $item['name'] ?? null,
            'slug' => $item['slug'],
            'content' => $values,
        ], $layout);

        $outcome['errors'] = $this->validationErrors($data);

        if ($outcome['errors'] === []) {
            $layout->update($data->layoutAttributes());
            $layout->syncContent($data->contentValues());
        }

        return $outcome;
    }
}
