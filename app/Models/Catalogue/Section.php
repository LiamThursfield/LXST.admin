<?php

namespace App\Models\Catalogue;

use Database\Factories\Catalogue\SectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A part of a catalogue, e.g. starters, colour treatments or bouquets
 *
 * @property int $id
 * @property int $catalogue_id
 * @property string $name
 * @property string|null $description
 * @property int $order
 * @property-read Catalogue $catalogue
 * @property-read Collection<int, Item> $items
 */
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected $table = 'catalogue_sections';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'catalogue_id',
        'name',
        'description',
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
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Catalogue, $this>
     */
    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('order')->orderBy('id');
    }
}
