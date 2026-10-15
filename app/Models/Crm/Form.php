<?php

namespace App\Models\Crm;

use Database\Factories\Crm\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A form that tenant websites render and submit (see #39), built from an ordered list of fields
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property list<string>|null $email_recipients
 * @property bool $marketing_email
 * @property bool $marketing_sms
 * @property bool $marketing_telephone
 * @property string|null $submit_button_text
 * @property string|null $redirect_url
 * @property string|null $success_message
 * @property Carbon|null $created_at
 * @property-read Collection<int, FormField> $fields
 * @property-read int|null $fields_count
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm_forms';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email_recipients',
        'marketing_email',
        'marketing_sms',
        'marketing_telephone',
        'submit_button_text',
        'redirect_url',
        'success_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_recipients' => 'array',
            'marketing_email' => 'boolean',
            'marketing_sms' => 'boolean',
            'marketing_telephone' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Generate a unique slug from the name when one isn't given
        static::saving(function (Form $form) {
            if (blank($form->slug)) {
                $form->slug = static::uniqueSlugFor($form->name, $form->id);
            }
        });
    }

    /**
     * Returns a slug for the name that no other form uses (including deleted ones), adding a numeric suffix if needed
     */
    public static function uniqueSlugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * The form's fields, in order
     *
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }
}
