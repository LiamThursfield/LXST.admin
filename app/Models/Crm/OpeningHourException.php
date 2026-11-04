<?php

namespace App\Models\Crm;

use Carbon\CarbonInterface;
use Database\Factories\Crm\OpeningHourExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dates that don't follow a location's weekly hours: a closure, a holiday or special hours. Without
 * periods it's closed on those dates; with them, those are its hours instead.
 *
 * @property int $id
 * @property int $organisation_unit_id
 * @property string $name
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property list<array{opens_at: string, closes_at: string, label?: string|null}>|null $periods
 * @property-read OrganisationUnit $organisationUnit
 */
class OpeningHourException extends Model
{
    /** @use HasFactory<OpeningHourExceptionFactory> */
    use HasFactory;

    protected $table = 'crm_opening_hour_exceptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organisation_unit_id',
        'name',
        'starts_on',
        'ends_on',
        'periods',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'periods' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OrganisationUnit, $this>
     */
    public function organisationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class);
    }

    /**
     * Whether the location is closed on these dates, rather than having special hours
     */
    public function isClosed(): bool
    {
        return empty($this->periods);
    }

    /**
     * Whether the date (Y-m-d) falls within this exception
     */
    public function covers(string $date): bool
    {
        return $date >= $this->starts_on->toDateString() && $date <= $this->ends_on->toDateString();
    }
}
