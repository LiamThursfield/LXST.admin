<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\ServiceOptionType;
use Database\Factories\Bookings\ServiceOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A variation or add-on of a service, e.g. long hair, extra guests or deluxe
 *
 * @property int $id
 * @property int $service_id
 * @property ServiceOptionType $type
 * @property string $name
 * @property int $price_adjustment In minor units, e.g. pence
 * @property int $duration_adjustment In minutes
 * @property int $order
 */
class ServiceOption extends Model
{
    /** @use HasFactory<ServiceOptionFactory> */
    use HasFactory;

    protected $table = 'bookings_service_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'type',
        'name',
        'price_adjustment',
        'duration_adjustment',
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
            'type' => ServiceOptionType::class,
            'price_adjustment' => 'integer',
            'duration_adjustment' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
