<?php

namespace App\Services\Cms\Portable;

use App\Data\Cms\MenuItemData;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\Url;
use App\Models\FileManager\File;
use Illuminate\Support\Collection;

/**
 * Turns pages, layouts, menus and redirects into portable items. A page brings what it needs: its
 * parents, template, layout and saved components. Whether a page is live, and its page views, belong
 * to the environment, so they're never included.
 */
class SiteExporter
{
    public function __construct(
        protected PortableContent $portable,
        protected TemplateExporter $templates,
        protected SavedComponentExporter $savedComponents,
        protected PortableValues $values,
    ) {}

    /**
     * @param  iterable<Page>  $pages
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function pages(iterable $pages): array
    {
        $withParents = [];

        foreach ($pages as $page) {
            $this->addWithParents($page, $withParents);
        }

        $layouts = Layout::query()->whereKey(array_map(fn (Page $page) => $page->layout_id, $withParents))->get();

        return $this->portable->wrap([
            ...$this->dependencyItems([...$withParents, ...$layouts->all()]),
            ...$layouts->map($this->layoutItem(...))->all(),
            ...array_map($this->pageItem(...), array_values($withParents)),
        ]);
    }

    /**
     * @param  array<int, Page>  $pages
     */
    protected function addWithParents(Page $page, array &$pages): void
    {
        if (isset($pages[$page->id])) {
            return;
        }

        if ($page->parent !== null) {
            $this->addWithParents($page->parent, $pages);
        }

        $pages[$page->id] = $page;
    }

    /**
     * @param  iterable<Layout>  $layouts
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function layouts(iterable $layouts): array
    {
        $layouts = collect($layouts);

        return $this->portable->wrap([
            ...$this->dependencyItems($layouts->all()),
            ...$layouts->map($this->layoutItem(...))->all(),
        ]);
    }

    /**
     * The templates and saved components that pages and layouts use, with the templates first
     *
     * @param  array<int|string, Page|Layout>  $owners
     * @return list<array<string, mixed>>
     */
    protected function dependencyItems(array $owners): array
    {
        $savedComponentIds = [];
        $templates = new Collection;

        foreach ($owners as $owner) {
            $templates->push($owner->template);
            $savedComponentIds = [...$savedComponentIds, ...SavedComponent::referencedIdsIn($owner->content()->pluck('data')->all())];
        }

        $saved = SavedComponent::query()->whereKey(array_unique($savedComponentIds))->get();

        foreach ($saved as $savedComponent) {
            $templates->push($savedComponent->template);
        }

        return [
            ...$this->templates->items($templates->unique('id')->values()),
            ...$saved->map($this->savedComponents->item(...))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pageItem(Page $page): array
    {
        $template = $page->template()->with('fields')->firstOrFail();

        return [
            'kind' => 'page',
            'url' => $page->url?->url_full,
            'name' => $page->name,
            'template' => $this->templateReference($template),
            'layout' => $page->layout->slug,
            'metadata' => $this->metadata($page),
            'content' => $this->values->export($template, $page->contentValues()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(Page $page): array
    {
        $metadata = $page->metadata;

        if ($metadata === null) {
            return [];
        }

        $path = File::query()->whereKey($metadata->og_image_file_id)->value('path');

        return [
            ...collect(Metadata::FIELDS)->reject(fn (string $field) => $field === 'og_image_file_id')
                ->mapWithKeys(fn (string $field) => [$field => $metadata->{$field}])
                ->all(),
            'og_image' => $path === null ? null : ['file' => $path],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutItem(Layout $layout): array
    {
        $template = $layout->template()->with('fields')->firstOrFail();

        return [
            'kind' => 'layout',
            'slug' => $layout->slug,
            'name' => $layout->name,
            'template' => $this->templateReference($template),
            'content' => $this->values->export($template, $layout->contentValues()),
        ];
    }

    /**
     * @return array{type: string, slug: string}
     */
    protected function templateReference(Template $template): array
    {
        return ['type' => $template->type->value, 'slug' => $template->slug];
    }

    /**
     * @param  iterable<Menu>  $menus
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function menus(iterable $menus): array
    {
        return $this->portable->wrap(collect($menus)->map($this->menuItem(...))->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function menuItem(Menu $menu): array
    {
        return [
            'kind' => 'menu',
            'slug' => $menu->slug,
            'name' => $menu->name,
            'items' => $this->menuItems($menu->menu_items ?? []),
        ];
    }

    /**
     * Links to pages by their URL, leaving out any whose page has gone
     *
     * @param  iterable<MenuItemData>  $items
     * @return list<array<string, mixed>>
     */
    protected function menuItems(iterable $items): array
    {
        $portable = [];

        foreach ($items as $item) {
            $isPage = $item->link_type === MenuItemData::LINK_PAGE;
            $url = $isPage ? Url::query()->where('urlable_type', Page::class)->where('urlable_id', $item->page_id)->value('url_full') : null;

            if ($isPage && $url === null) {
                continue;
            }

            $portable[] = [
                'label' => $item->label,
                'link_type' => $item->link_type,
                ...($isPage ? ['page' => $url] : ['url' => $item->url]),
                'target' => $item->target,
                'rel' => $item->rel,
                'custom_class' => $item->custom_class,
                'children' => $this->menuItems($item->children),
            ];
        }

        return $portable;
    }

    /**
     * @param  iterable<Redirect>  $redirects
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function redirects(iterable $redirects): array
    {
        return $this->portable->wrap(collect($redirects)->map($this->redirectItem(...))->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function redirectItem(Redirect $redirect): array
    {
        return [
            'kind' => 'redirect',
            'url_from' => $redirect->url_from,
            'url_to' => $redirect->url_to,
            'is_enabled' => $redirect->is_enabled,
            'is_permanent' => $redirect->is_permanent,
        ];
    }
}
