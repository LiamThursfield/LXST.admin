<?php

namespace App\Http\Resources\Tenant\Admin\Catalogue;

use App\Http\Resources\Traits\HasActions;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Section;
use App\Services\FileManager\FileManagerStorage;
use App\Services\Industry\Terminology;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalogue for the list, and with its sections and items when they're loaded. Prices for the
 * editor are in major units; display prices are in minor units.
 */
class CatalogueResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Catalogue $catalogue */
        $catalogue = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $catalogue)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.catalogue.catalogues.show', $catalogue))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $catalogue)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.catalogue.catalogues.edit', $catalogue))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $catalogue)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.catalogue.catalogues.destroy', $catalogue))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(app(Terminology::class)->translate('catalogue.catalogues.confirm_delete', ['name' => $catalogue->name]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Catalogue $catalogue */
        $catalogue = $this->resource;

        return $this->appendActions($request, [
            'id' => $catalogue->id,
            'name' => $catalogue->name,
            'slug' => $catalogue->slug,
            'description' => $catalogue->description,
            'is_published' => $catalogue->is_published,
            'available_days' => $catalogue->available_days ?? [],
            'available_from' => $catalogue->available_from === null ? null : substr($catalogue->available_from, 0, 5),
            'available_until' => $catalogue->available_until === null ? null : substr($catalogue->available_until, 0, 5),
            'starts_on' => $catalogue->starts_on?->toDateString(),
            'ends_on' => $catalogue->ends_on?->toDateString(),
            'schedule' => $catalogue->scheduleSummary(),
            'applies_now' => $catalogue->appliesAt(now()),
            'sections_count' => $catalogue->sections_count ?? null,
            'items_count' => $catalogue->items_count ?? null,
            // E.g. "3 sections · 8 items", for the list
            'contents' => $catalogue->sections_count === null ? null : trans_choice('catalogue.catalogues.sections_count', $catalogue->sections_count)
                .' · '.trans_choice('catalogue.catalogues.items_count', (int) $catalogue->items_count),
            'url' => route('admin.catalogue.catalogues.show', $catalogue),
            'sections' => $this->whenLoaded('sections', fn () => $catalogue->sections
                ->map(fn (Section $section) => $this->section($section))
                ->values()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function section(Section $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'description' => $section->description,
            'items' => $section->items->map(fn (Item $item) => $this->item($item))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(Item $item): array
    {
        $storage = app(FileManagerStorage::class);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'service_id' => $item->service_id,
            'service_name' => $item->service?->name,
            'image_file_id' => $item->image_file_id,
            'image_url' => $item->image === null ? null : $storage->url($item->image),
            'price' => $item->price === null ? null : $this->toMajorUnits($item->price),
            'display_price' => $item->displayPrice(),
            'is_available' => $item->is_available,
            'tracks_stock' => $item->tracks_stock,
            'stock' => $item->stock,
            'in_stock' => $item->isInStock(),
            'tag_ids' => $item->tags->pluck('id')->values(),
            'variations' => $item->variations->map(fn (ItemVariation $variation) => [
                'id' => $variation->id,
                'name' => $variation->name,
                'price' => $this->toMajorUnits($variation->price),
                'price_minor' => $variation->price,
                'stock' => $variation->stock,
            ])->values(),
        ];
    }

    protected function toMajorUnits(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
