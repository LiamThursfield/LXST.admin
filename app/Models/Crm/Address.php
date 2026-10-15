<?php

namespace App\Models\Crm;

use Database\Factories\Crm\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A UK-style postal address, attached to any CRM model (organisation units, contacts...)
 *
 * @property int $id
 * @property string $postcode
 * @property string $line_1
 * @property string|null $line_2
 * @property string|null $line_3
 * @property string|null $town_city
 * @property string|null $county
 * @property string|null $country
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $table = 'crm_addresses';

    /**
     * The address fields that can be filled from a form.
     *
     * @var list<string>
     */
    public const array FIELDS = [
        'postcode',
        'line_1',
        'line_2',
        'line_3',
        'town_city',
        'county',
        'country',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = self::FIELDS;

    /**
     * The model the address belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
