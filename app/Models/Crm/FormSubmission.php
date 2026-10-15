<?php

namespace App\Models\Crm;

use App\Data\Crm\FormSubmissionData;
use Database\Factories\Crm\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A submission of a CRM form by a (possibly unknown) contact
 *
 * @property int $id
 * @property int $form_id
 * @property int|null $contact_id
 * @property bool $is_spam
 * @property FormSubmissionData $data
 * @property Carbon $submitted_at
 * @property-read Form $form
 * @property-read Contact|null $contact
 */
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    protected $table = 'crm_form_submissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'contact_id',
        'is_spam',
        'data',
        'submitted_at',
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
            'data' => FormSubmissionData::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * The form, even if it has since been deleted
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /**
     * The contact, even if they have since been deleted
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
