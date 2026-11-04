<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Bookings\TimeOffType;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\TimeOffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whole days a resource isn't available, once approved
 *
 * @property int $id
 * @property int $resource_id
 * @property TimeOffType $type
 * @property TimeOffStatus $status
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property string|null $reason
 * @property int|null $requested_by_id
 * @property int|null $reviewed_by_id
 * @property CarbonInterface|null $reviewed_at
 */
class TimeOff extends Model
{
    /** @use HasFactory<TimeOffFactory> */
    use HasFactory;

    protected $table = 'bookings_time_off';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resource_id',
        'type',
        'status',
        'starts_on',
        'ends_on',
        'reason',
        'requested_by_id',
        'reviewed_by_id',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TimeOffType::class,
            'status' => TimeOffStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The resource, even when since deleted
     *
     * @return BelongsTo<BookableResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * Scope to the time off touching a range of dates
     *
     * @param  Builder<TimeOff>  $query
     * @return Builder<TimeOff>
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }

    public function covers(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date && $this->ends_on->toDateString() >= $date;
    }
}
