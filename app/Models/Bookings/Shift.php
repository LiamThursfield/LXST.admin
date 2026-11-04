<?php

namespace App\Models\Bookings;

use Carbon\CarbonInterface;
use Database\Factories\Bookings\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period on the rota. A date's shifts replace that weekday's working hours.
 *
 * @property int $id
 * @property int $resource_id
 * @property CarbonInterface $works_on
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $note
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $table = 'bookings_shifts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resource_id',
        'works_on',
        'starts_at',
        'ends_at',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'works_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<BookableResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class);
    }
}
