<?php

namespace App\Services\Cms\Portable;

use App\Data\Cms\MenuItemData;
use App\Http\Requests\Tenant\Admin\Cms\ImportedMenuData;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\Url;
use Illuminate\Database\Eloquent\Model;

/**
 * Imports menus, by their slug. The record is made first, and its items filled in once every page
 * exists, as menus and pages can point at each other.
 *
 * @extends ItemImporter<Menu>
 */
class MenuImporter extends ItemImporter
{
    use ChecksImportedData;

    public function __construct(protected SiteExporter $exporter) {}

    public function kind(): string
    {
        return 'menu';
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
        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function typeLabel(array $item): string
    {
        return __('cms.import.kinds.menu');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Menu
    {
        return Menu::query()->where('slug', $item['slug'])->first();
    }

    /**
     * @param  Menu  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->menuItem($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Menu::class) ?? false;
    }

    /**
     * @param  Menu  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        return [(string) __('cms.import.warnings.menu_live')];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Menu|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        if ($existing === null) {
            Menu::create(['name' => $this->placeholderName($item), 'slug' => $item['slug'], 'menu_items' => []]);
        }

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function fill(array $item): array
    {
        $menu = $this->existing($item);

        if ($menu === null) {
            return ['errors' => [], 'warnings' => []];
        }

        $warnings = [];

        $data = ImportedMenuData::for([
            'name' => $item['name'] ?? null,
            'slug' => $item['slug'],
            'menu_items' => $this->builderItems($item['items'] ?? [], $warnings),
        ], $menu);

        $errors = $this->validationErrors($data);

        if ($errors === []) {
            $menu->update($data->menuAttributes());
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * The items as the menu editor would send them, with pages found by their URL. An item whose page
     * isn't here is left out, as an item has to go somewhere.
     *
     * @param  list<string>  $warnings
     */
    protected function builderItems(mixed $items, array &$warnings): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        $builderItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $builderItems[] = $item;

                continue;
            }

            $builderItem = [
                'label' => $item['label'] ?? null,
                'link_type' => $item['link_type'] ?? null,
                'target' => $item['target'] ?? null,
                'rel' => $item['rel'] ?? null,
                'custom_class' => $item['custom_class'] ?? null,
                'children' => $this->builderItems($item['children'] ?? [], $warnings),
            ];

            if (($item['link_type'] ?? null) !== MenuItemData::LINK_PAGE) {
                $builderItems[] = [...$builderItem, 'url' => $item['url'] ?? null];

                continue;
            }

            $pageId = is_string($item['page'] ?? null)
                ? Url::query()->where('urlable_type', Page::class)->where('url_full', $item['page'])->value('urlable_id')
                : null;

            if ($pageId === null) {
                $warnings[] = (string) __('cms.import.missing.menu_item', [
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '?',
                    'page' => is_string($item['page'] ?? null) ? $item['page'] : '?',
                ]);

                continue;
            }

            $builderItems[] = [...$builderItem, 'page_id' => $pageId];
        }

        return $builderItems;
    }
}
