<?php

namespace App\Services\Catalogue;

use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\Section;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves a catalogue's sections, items and variations from the editor's full list, in the order
 * given. Ones with an id are kept and updated (an item can move to another section), new ones are
 * created, and any left out are deleted.
 *
 * @phpstan-type VariationData array{id: int|null, name: string, price: int, stock: int|null}
 * @phpstan-type ItemData array{id: int|null, name: string, description: string|null, service_id: int|null, image_file_id: int|null, price: int|null, is_available: bool, tracks_stock: bool, stock: int|null, tag_ids: list<int>, variations: list<VariationData>}
 * @phpstan-type SectionData array{id: int|null, name: string, description: string|null, items: list<ItemData>}
 */
class CatalogueContents
{
    /**
     * @param  list<SectionData>  $sections  Ids must already be checked to belong to the catalogue
     */
    public function sync(Catalogue $catalogue, array $sections): void
    {
        DB::transaction(function () use ($catalogue, $sections) {
            $keptItemIds = [];
            $keptSectionIds = [];

            foreach ($sections as $sectionOrder => $sectionData) {
                /** @var Section $section */
                $section = $catalogue->sections()->updateOrCreate(['id' => $sectionData['id']], [
                    'name' => $sectionData['name'],
                    'description' => $sectionData['description'],
                    'order' => $sectionOrder,
                ]);
                $keptSectionIds[] = $section->id;

                foreach ($sectionData['items'] as $itemOrder => $itemData) {
                    $keptItemIds[] = $this->saveItem($section, $itemData, $itemOrder)->id;
                }
            }

            // Items go before sections, so one moved out of a deleted section isn't deleted with it
            Item::query()
                ->whereIn('section_id', $catalogue->sections()->select('id'))
                ->whereNotIn('id', $keptItemIds)
                ->delete();

            $catalogue->sections()->whereNotIn('id', $keptSectionIds)->delete();
        });
    }

    /**
     * @param  ItemData  $data
     */
    protected function saveItem(Section $section, array $data, int $order): Item
    {
        $hasVariations = $data['variations'] !== [];
        // A service sets the price, and so do variations
        $usesOwnPrice = $data['service_id'] === null && ! $hasVariations;

        $item = Item::query()->updateOrCreate(['id' => $data['id']], [
            'section_id' => $section->id,
            'service_id' => $data['service_id'],
            'image_file_id' => $data['image_file_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $usesOwnPrice ? $data['price'] : null,
            'is_available' => $data['is_available'],
            'tracks_stock' => $data['tracks_stock'],
            // Each variation counts its own
            'stock' => $data['tracks_stock'] && ! $hasVariations ? ($data['stock'] ?? 0) : null,
            'order' => $order,
        ]);

        $item->tags()->sync($data['tag_ids']);

        $keptVariationIds = [];
        $existingVariationIds = $item->variations()->pluck('id')->all();

        foreach ($data['variations'] as $variationOrder => $variation) {
            // One of another item's, when the editor moved it, is made afresh for this item
            $id = in_array($variation['id'], $existingVariationIds, true) ? $variation['id'] : null;

            $keptVariationIds[] = $item->variations()->updateOrCreate(['id' => $id], [
                ...Arr::except($variation, 'id'),
                'stock' => $data['tracks_stock'] ? ($variation['stock'] ?? 0) : null,
                'order' => $variationOrder,
            ])->id;
        }

        $item->variations()->whereNotIn('id', $keptVariationIds)->delete();

        return $item;
    }
}
