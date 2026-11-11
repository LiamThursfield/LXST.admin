<?php

namespace App\Models\Bookings;

use Carbon\CarbonInterface;
use Database\Factories\Bookings\LineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line on a quote or an invoice, e.g. "Labour, 3 hours at £40.00"
 *
 * @property int $id
 * @property string $document_type
 * @property int $document_id
 * @property string $description
 * @property string $quantity A decimal, e.g. 2.50
 * @property int $unit_price In minor units
 * @property int $total In minor units
 * @property int $order
 * @property CarbonInterface|null $created_at
 * @property-read Quote|Invoice $document
 */
class LineItem extends Model
{
    /** @use HasFactory<LineItemFactory> */
    use HasFactory;

    protected $table = 'bookings_line_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'description',
        'quantity',
        'unit_price',
        'total',
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
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'total' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }
}
