<?php

namespace App\Models\Catalogue;

use App\Enums\Catalogue\TagKind;
use Database\Factories\Catalogue\TagFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label on catalogue items: an allergen, a dietary label such as vegan, or the tenant's own, such
 * as "New". Built-in ones keep the key they were added from, so a website can show its own icon.
 *
 * @property int $id
 * @property string|null $preset
 * @property TagKind $kind
 * @property string $name
 * @property string|null $icon E.g. "i-lucide-leaf"
 * @property int $order
 * @property-read Collection<int, Item> $items
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $table = 'catalogue_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'preset',
        'kind',
        'name',
        'icon',
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
            'kind' => TagKind::class,
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'catalogue_item_tag', 'tag_id', 'item_id');
    }
}
