<?php

namespace App\Models\Catalogue;

use App\Enums\Bookings\ServicePriceType;
use App\Models\Bookings\Service;
use App\Models\FileManager\File;
use Database\Factories\Catalogue\ItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thing in a catalogue section: a dish, a treatment, a product. It can have variations with
 * their own prices, show a bookable service and use its price, and count its stock.
 *
 * @property int $id
 * @property int $section_id
 * @property int|null $service_id
 * @property int|null $image_file_id
 * @property string $name
 * @property string|null $description
 * @property int|null $price In minor units, e.g. pence. Ignored when it has variations or a service.
 * @property bool $is_available False when it's sold out for now
 * @property bool $tracks_stock
 * @property int|null $stock Counted here unless it has variations, which count their own
 * @property int $order
 * @property-read Section $section
 * @property-read Service|null $service
 * @property-read File|null $image
 * @property-read Collection<int, ItemVariation> $variations
 * @property-read Collection<int, Tag> $tags
 */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected $table = 'catalogue_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'section_id',
        'service_id',
        'image_file_id',
        'name',
        'description',
        'price',
        'is_available',
        'tracks_stock',
        'stock',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_available' => 'boolean',
            'tracks_stock' => 'boolean',
            'stock' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * The bookable service it shows, if any. A deleted service is left out.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }

    /**
     * @return HasMany<ItemVariation, $this>
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ItemVariation::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'catalogue_item_tag', 'item_id', 'tag_id')
            ->orderBy('order')
            ->orderBy('name');
    }

    /**
     * The price to show, and whether it's a starting price: the service's when it shows one, the
     * cheapest variation's when it has them, otherwise its own. Null when it has no price.
     *
     * @return array{amount: int|null, from: bool}
     */
    public function displayPrice(): array
    {
        if ($this->service_id !== null && $this->service !== null) {
            $service = $this->service;

            return $service->price_type->hasAmount()
                ? ['amount' => $service->price, 'from' => $service->price_type === ServicePriceType::From]
                : ['amount' => null, 'from' => false];
        }

        if ($this->variations->isNotEmpty()) {
            $prices = $this->variations->pluck('price');

            return ['amount' => (int) $prices->min(), 'from' => $prices->unique()->count() > 1];
        }

        return ['amount' => $this->price, 'from' => false];
    }

    /**
     * Whether any is left, counting each variation when it has them. Always true when stock isn't
     * tracked.
     */
    public function isInStock(): bool
    {
        if (! $this->tracks_stock) {
            return true;
        }

        if ($this->variations->isNotEmpty()) {
            return $this->variations->contains(fn (ItemVariation $variation) => ($variation->stock ?? 0) > 0);
        }

        return ($this->stock ?? 0) > 0;
    }

    /**
     * Whether it can be had now: not marked as sold out, and in stock
     */
    public function isAvailableNow(): bool
    {
        return $this->is_available && $this->isInStock();
    }
}
