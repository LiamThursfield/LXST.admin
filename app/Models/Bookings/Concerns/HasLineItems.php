<?php

namespace App\Models\Bookings\Concerns;

use App\Models\Bookings\LineItem;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A quote or an invoice: lines that add up to a subtotal, with any tax on top
 */
trait HasLineItems
{
    /**
     * @return MorphMany<LineItem, $this>
     */
    public function lines(): MorphMany
    {
        return $this->morphMany(LineItem::class, 'document')->orderBy('order')->orderBy('id');
    }
}
