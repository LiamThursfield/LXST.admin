<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\PublishStatus;
use App\Http\Requests\Tenant\Admin\Cms\ImportedPageData;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Url;
use App\Models\FileManager\File;
use App\Services\Cms\PageUrls;
use Illuminate\Database\Eloquent\Model;

/**
 * Imports pages, by their full URL, which also says where they sit: a page's parent is the URL above
 * it. The record is made first, and its content filled in once everything it could refer to exists,
 * as menus and pages can point at each other.
 *
 * New pages arrive disabled, so nothing goes live by accident, and an overwritten page keeps its own
 * publish state and dates.
 *
 * @extends ItemImporter<Page>
 *
 * @phpstan-import-type MissingReference from PortableValues
 */
class PageImporter extends ItemImporter
{
    use ChecksImportedData;

    public function __construct(
        protected SiteExporter $exporter,
        protected PortableValues $values,
        protected PageUrls $urls,
    ) {}

    public function kind(): string
    {
        return 'page';
    }

    /**
     * @param  array<mixed>  $item
     */
    public function key(array $item): ?string
    {
        $url = $item['url'] ?? null;

        return is_string($url) && strlen($url) <= 255 && preg_match('#^/([a-z0-9]+(-[a-z0-9]+)*(/[a-z0-9]+(-[a-z0-9]+)*)*)?$#', $url) === 1
            ? "{$this->kind()}/{$url}"
            : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function identifier(array $item): string
    {
        return $item['url'];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function dependencies(array $item): array
    {
        $parent = $this->parentUrl($item['url']);

        return array_values(array_filter([
            $this->templateKey($item['template'] ?? null),
            is_string($item['layout'] ?? null) ? "layout/{$item['layout']}" : null,
            $parent === null ? null : "{$this->kind()}/{$parent}",
        ]));
    }

    /**
     * The URL above this one, or null for a top-level page
     */
    protected function parentUrl(string $url): ?string
    {
        $parent = substr($url, 0, (int) strrpos($url, '/'));

        return $parent === '' ? null : $parent;
    }

    /**
     * This page's own part of its URL
     */
    protected function slug(string $url): string
    {
        return substr($url, (int) strrpos($url, '/') + 1);
    }

    protected function pageAt(?string $url): ?Page
    {
        $id = $url === null ? null : Url::query()->where('urlable_type', Page::class)->where('url_full', $url)->value('urlable_id');

        return $id === null ? null : Page::query()->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function typeLabel(array $item): string
    {
        return __('cms.import.kinds.page');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Page
    {
        return $this->pageAt($item['url']);
    }

    /**
     * @param  Page  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->pageItem($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Page::class) ?? false;
    }

    /**
     * @return list<string>
     */
    protected function createNotes(): array
    {
        return [(string) __('cms.import.changes.arrives_disabled')];
    }

    /**
     * @param  Page  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        $template = $this->referencedTemplate($item['template'] ?? null);

        return array_values(array_filter([
            $existing->url?->status() === PublishStatus::Published ? (string) __('cms.import.warnings.page_live') : null,
            $template !== null && $template->id !== $existing->template_id ? (string) __('cms.import.warnings.page_template') : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Page|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $parentUrl = $this->parentUrl($item['url']);
        $parent = $this->pageAt($parentUrl);
        $template = $this->referencedTemplate($item['template'] ?? null);
        $layout = $this->layout($item);
        $slug = $this->slug($item['url']);

        $errors = array_values(array_filter([
            $parentUrl !== null && $parent === null ? (string) __('cms.import.validation.missing_parent', ['url' => $parentUrl]) : null,
            $template === null ? $this->missingTemplateMessage($item['template'] ?? null) : null,
            $layout === null ? (string) __('cms.import.validation.missing_layout', ['layout' => is_string($item['layout'] ?? null) ? $item['layout'] : '?']) : null,
            strlen($slug) > 120 ? (string) __('cms.pages.validation.slug_format') : null,
        ]));

        if ($errors !== [] || $template === null || $layout === null) {
            return ['errors' => $errors, 'warnings' => []];
        }

        if ($existing === null) {
            $page = Page::create([
                'template_id' => $template->id,
                'layout_id' => $layout->id,
                'parent_id' => $parent?->id,
                'name' => $this->placeholderName($item),
                'slug' => $slug,
            ]);

            $this->urls->sync($page, ['is_enabled' => false, 'published_at' => null, 'expired_at' => null]);
        }

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function layout(array $item): ?Layout
    {
        return is_string($item['layout'] ?? null) ? Layout::query()->where('slug', $item['layout'])->first() : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function fill(array $item): array
    {
        $page = $this->pageAt($item['url']);
        $template = $this->referencedTemplate($item['template'] ?? null);

        if ($page === null || $template === null) {
            return ['errors' => [], 'warnings' => []];
        }

        /** @var list<MissingReference> $missing */
        $missing = [];
        $values = $this->values->import($template, is_array($item['content'] ?? null) ? $item['content'] : [], $missing);
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $imageId = $this->ogImageId($metadata, $missing);
        $outcome = $this->missingOutcome($missing);

        if ($outcome['errors'] !== []) {
            return $outcome;
        }

        $data = ImportedPageData::for([
            'template_id' => $template->id,
            'layout_id' => $this->layout($item)?->id,
            'parent_id' => $page->parent_id,
            'name' => $item['name'] ?? null,
            'slug' => $page->slug,
            // Only here for the rules: whether it's live stays as it is
            'is_enabled' => false,
            'metadata' => [...$metadata, 'og_image' => null, 'og_image_file_id' => $imageId],
            'content' => $values,
        ], $page);

        $outcome['errors'] = $this->validationErrors($data);

        if ($outcome['errors'] === []) {
            $page->update($data->pageAttributes());
            $page->metadata()->updateOrCreate([], $data->metadataAttributes());
            $page->syncContent($data->contentValues());
        }

        return $outcome;
    }

    /**
     * @param  array<mixed>  $metadata
     * @param  list<MissingReference>  $missing
     */
    protected function ogImageId(array $metadata, array &$missing): ?int
    {
        $path = is_array($metadata['og_image'] ?? null) ? ($metadata['og_image']['file'] ?? null) : null;

        if (! is_string($path)) {
            return null;
        }

        $id = File::query()->where('path', $path)->value('id');

        if ($id === null) {
            $missing[] = ['field' => (string) __('cms.import.og_image'), 'required' => false, 'kind' => 'file', 'reference' => $path];
        }

        return $id;
    }
}
