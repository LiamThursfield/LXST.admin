<?php

namespace App\Http\Resources\Tenant\Api\V1\Catalogue;

use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Section;
use App\Models\Catalogue\Tag;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published catalogue as a website, its public page and its printed copy show it: when it applies,
 * and each section's items with their prices, variations, tags and whether they can be had now.
 * Prices are in minor units, with the formatted price beside them.
 */
class CatalogueResource extends JsonResource
{
    /** What to load for the contents */
    public const array RELATIONS = [
        'sections.items.variations',
        'sections.items.tags',
        'sections.items.service',
        'sections.items.image',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Catalogue $catalogue */
        $catalogue = $this->resource;

        return [
            'slug' => $catalogue->slug,
            'name' => $catalogue->name,
            'description' => $catalogue->description,
            'currency' => Catalogue::CURRENCY,
            'schedule' => [
                'summary' => $catalogue->scheduleSummary(),
                'days' => $catalogue->available_days,
                'from' => $catalogue->available_from === null ? null : substr($catalogue->available_from, 0, 5),
                'until' => $catalogue->available_until === null ? null : substr($catalogue->available_until, 0, 5),
                'starts_on' => $catalogue->starts_on?->toDateString(),
                'ends_on' => $catalogue->ends_on?->toDateString(),
            ],
            // Right now, so this mustn't be cached
            'applies_now' => $catalogue->appliesAt(now()),
            'url' => route('catalogue.public.show', $catalogue->slug),
            'api_url' => route('api.v1.catalogue.catalogues.show', $catalogue->slug),
            'sections' => $this->whenLoaded('sections', fn () => $catalogue->sections
                ->map(fn (Section $section) => $this->section($section))
                ->values()
                ->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function section(Section $section): array
    {
        return [
            'name' => $section->name,
            'description' => $section->description,
            'items' => $section->items->map(fn (Item $item) => $this->item($item))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(Item $item): array
    {
        $price = $item->displayPrice();

        return [
            'name' => $item->name,
            'description' => $item->description,
            'image' => $item->image === null ? null : [
                'url' => app(FileManagerStorage::class)->url($item->image),
                'alt' => $item->image->alt_text,
            ],
            'price' => $price['amount'] === null ? null : [
                'amount' => $price['amount'],
                'formatted' => $this->format($price['amount']),
                'from' => $price['from'],
            ],
            // Whether it can be had now: not sold out, and in stock
            'available' => $item->isAvailableNow(),
            'sold_out' => ! $item->is_available,
            'in_stock' => $item->isInStock(),
            'service' => $item->service === null ? null : [
                'name' => $item->service->name,
                'duration_minutes' => $item->service->duration_minutes,
            ],
            'tags' => $item->tags->map(fn (Tag $tag) => [
                'name' => $tag->name,
                'kind' => $tag->kind->value,
                'icon' => $tag->icon,
                'preset' => $tag->preset,
            ])->values()->all(),
            'variations' => $item->variations->map(fn (ItemVariation $variation) => [
                'name' => $variation->name,
                'price' => ['amount' => $variation->price, 'formatted' => $this->format($variation->price)],
                'in_stock' => ! $item->tracks_stock || ($variation->stock ?? 0) > 0,
            ])->values()->all(),
        ];
    }

    protected function format(int $amount): string
    {
        return Catalogue::formatPrice($amount);
    }
}
