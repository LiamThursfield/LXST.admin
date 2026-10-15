<?php

namespace App\Models\Crm;

use App\Data\Crm\FormFieldSettings;
use App\Enums\Crm\FormFieldType;
use Database\Factories\Crm\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field of a CRM form
 *
 * @property int $id
 * @property int $form_id
 * @property FormFieldType $type
 * @property string $name
 * @property string $slug
 * @property bool $is_required
 * @property int $order
 * @property FormFieldSettings|null $settings
 */
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    protected $table = 'crm_form_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'type',
        'name',
        'slug',
        'is_required',
        'order',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'is_required' => 'boolean',
            'order' => 'integer',
            'settings' => FormFieldSettings::class,
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
