<?php

namespace App\Models\Catalogue;

use Database\Factories\Catalogue\ItemVariationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way an item comes, with its own price, e.g. small or large, a glass or a bottle
 *
 * @property int $id
 * @property int $item_id
 * @property string $name
 * @property int $price In minor units, e.g. pence
 * @property int|null $stock Counted when the item tracks stock
 * @property int $order
 * @property-read Item $item
 */
class ItemVariation extends Model
{
    /** @use HasFactory<ItemVariationFactory> */
    use HasFactory;

    protected $table = 'catalogue_item_variations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'name',
        'price',
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
            'stock' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
