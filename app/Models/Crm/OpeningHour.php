<?php

namespace App\Models\Crm;

use Database\Factories\Crm\OpeningHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period a location is open each week. A day with split shifts, such as lunch and dinner, has
 * one of these for each.
 *
 * @property int $id
 * @property int $organisation_unit_id
 * @property int $day_of_week ISO day of the week: 1 is Monday, 7 is Sunday
 * @property string $opens_at HH:MM in the location's timezone
 * @property string $closes_at HH:MM in the location's timezone. At or before opens_at, it's the next day.
 * @property string|null $label
 * @property-read OrganisationUnit $organisationUnit
 */
class OpeningHour extends Model
{
    /** @use HasFactory<OpeningHourFactory> */
    use HasFactory;

    protected $table = 'crm_opening_hours';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organisation_unit_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'label',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OrganisationUnit, $this>
     */
    public function organisationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class);
    }
}
