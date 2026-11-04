<?php

namespace App\Models\Bookings;

use Database\Factories\Bookings\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of services, e.g. "Cuts" or "Treatment rooms"
 *
 * @property int $id
 * @property string $name
 * @property int $order
 */
class ServiceCategory extends Model
{
    /** @use HasFactory<ServiceCategoryFactory> */
    use HasFactory;

    protected $table = 'bookings_service_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
