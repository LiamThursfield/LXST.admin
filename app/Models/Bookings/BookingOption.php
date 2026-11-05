<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\ServiceOptionType;
use Database\Factories\Bookings\BookingOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A copy of a variation or add-on chosen for a booking
 *
 * @property int $id
 * @property int $booking_id
 * @property int|null $service_option_id
 * @property ServiceOptionType $type
 * @property string $name
 * @property int $price_adjustment In minor units, e.g. pence
 * @property int $duration_adjustment In minutes
 */
class BookingOption extends Model
{
    /** @use HasFactory<BookingOptionFactory> */
    use HasFactory;

    protected $table = 'bookings_booking_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'service_option_id',
        'type',
        'name',
        'price_adjustment',
        'duration_adjustment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceOptionType::class,
            'price_adjustment' => 'integer',
            'duration_adjustment' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
