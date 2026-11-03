<?php

namespace App\Services\Cms\Portable;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports portable JSON. A dry run does the real import in a transaction that's always rolled back, so
 * what it reports is exactly what confirming would do, including the checks that depend on items
 * earlier in the same file.
 *
 * @phpstan-import-type ImportResult from ItemImporter
 */
class ContentImporter
{
    /** @var array<string, ItemImporter<*>> By kind */
    protected array $importers;

    public function __construct(
        protected PortableContent $portable,
        TemplateImporter $templates,
        SavedComponentImporter $savedComponents,
        FormImporter $forms,
        LayoutImporter $layouts,
        MenuImporter $menus,
        PageImporter $pages,
        RedirectImporter $redirects,
    ) {
        foreach ([$templates, $savedComponents, $forms, $layouts, $menus, $pages, $redirects] as $importer) {
            $this->importers[$importer->kind()] = $importer;
        }
    }

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
     * Checks the items are ones this version understands, each once. What's in them is checked later,
     * by the same rules as the admin.
     *
     * @return array<string, array<string, mixed>> By key
     *
     * @throws ValidationException
     */
    protected function validItems(string $json): array
    {
        $items = [];

        foreach ($this->portable->items($json) as $position => $item) {
            $importer = is_array($item) && is_string($item['kind'] ?? null) ? ($this->importers[$item['kind']] ?? null) : null;
            $key = $importer?->key($item);

            if ($key === null) {
                throw $this->portable->invalid(__('cms.import.validation.unknown_item', ['number' => $position + 1]));
            }

            if (isset($items[$key])) {
                throw $this->portable->invalid(__('cms.import.validation.duplicate_item', ['slug' => $item['slug']]));
            }

            /** @var array<string, mixed> $item */
            $items[$key] = $item;
        }

        return $items;
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

        // Menus, pages and layouts can point at each other, so no order would work for those: every
        // record is made first, then what refers to other records is filled in
        $results = array_map(
            fn (string $key) => $this->importers[$items[$key]['kind']]->import($key, $items[$key], in_array($key, $overwrite, true), isset($loops[$key])),
            array_keys($ordered),
        );

        return array_map(function (array $result) use ($items) {
            if ($result['errors'] !== [] || ! in_array($result['action'], [ItemImporter::ACTION_CREATE, ItemImporter::ACTION_OVERWRITE], true)) {
                return $result;
            }

            $outcome = $this->importers[$result['kind']]->fill($items[$result['key']]);

            return [
                ...$result,
                'errors' => $outcome['errors'],
                'warnings' => [...$result['warnings'], ...$outcome['warnings']],
            ];
        }, $results);
    }

    /**
     * Dependencies go first, whatever order the file has them in, so they exist by the time they're
     * needed. Items that depend on each other are noted, as no order would work.
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

        foreach ($this->importers[$items[$key]['kind']]->dependencies($items[$key]) as $dependency) {
            if (isset($items[$dependency])) {
                $this->addInDependencyOrder($dependency, $items, $ordered, $loops, [...$path, $key]);
            }
        }

        $ordered[$key] = true;
    }
}
