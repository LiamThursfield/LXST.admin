<?php

namespace App\Models\Crm;

use Database\Factories\Crm\ContactFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A person the tenant is in contact with, e.g. someone who submitted a form
 *
 * @property int $id
 * @property bool $is_spam
 * @property string|null $title
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $telephone
 * @property string $email
 * @property bool $marketing_email
 * @property bool $marketing_sms
 * @property bool $marketing_telephone
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property-read string $name
 * @property-read string $name_with_title
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, FormSubmission> $formSubmissions
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm_contacts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'is_spam',
        'title',
        'first_name',
        'last_name',
        'telephone',
        'email',
        'marketing_email',
        'marketing_sms',
        'marketing_telephone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_spam' => 'boolean',
            'marketing_email' => 'boolean',
            'marketing_sms' => 'boolean',
            'marketing_telephone' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A contact's submissions follow the contact's spam status
        static::updated(function (Contact $contact) {
            if ($contact->wasChanged('is_spam')) {
                $contact->formSubmissions()->update(['is_spam' => $contact->is_spam]);
            }
        });
    }

    /**
     * The first and last name
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim(($this->first_name ?? '').' '.($this->last_name ?? '')));
    }

    /**
     * The title, first and last name, e.g. "Dr Jane Smith"
     *
     * @return Attribute<string, never>
     */
    protected function nameWithTitle(): Attribute
    {
        return Attribute::get(fn (): string => trim(($this->title ?? '').' '.$this->name));
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
