<?php

namespace App\Services\Crm;

use App\Data\Crm\FormSubmissionAnswer;
use App\Data\Crm\FormSubmissionData;
use App\Mail\Crm\FormSubmittedMail;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records a public submission of a CRM form
 */
class FormSubmissionRecorder
{
    /**
     * Saves the submission, creating or updating the contact from the contact fields and marketing answers,
     * and emails the form's recipients unless it's spam.
     *
     * A submission that looks automated is saved as spam without touching any contact, so bots can't
     * create or overwrite contacts.
     *
     * @param  array<string, mixed>  $values  The validated answers, keyed by field key
     */
    public function record(Form $form, array $values, bool $looksAutomated = false): FormSubmission
    {
        $form->loadMissing('fields');

        $submission = DB::transaction(function () use ($form, $values, $looksAutomated) {
            $contact = $looksAutomated ? null : $this->saveContact($form, $values);

            return FormSubmission::create([
                'form_id' => $form->id,
                'contact_id' => $contact?->id,
                'is_spam' => $looksAutomated || ($contact->is_spam ?? false),
                'data' => new FormSubmissionData(
                    answers: array_values($form->fields->map(fn (FormField $field) => new FormSubmissionAnswer(
                        slug: $field->slug,
                        label: $field->name,
                        type: $field->type,
                        value: $this->answerValue($values[$field->slug] ?? null),
                    ))->all()),
                    marketing: $this->marketingAnswers($form, $values),
                ),
                'submitted_at' => now(),
            ]);
        });

        if (! $submission->is_spam && filled($form->email_recipients)) {
            Mail::to($form->email_recipients)->queue(new FormSubmittedMail($submission));
        }

        return $submission;
    }

    /**
     * Creates or updates the contact identified by the contact email field, restoring them if they were deleted.
     * Returns null when the form has no contact email answer.
     *
     * @param  array<string, mixed>  $values
     */
    protected function saveContact(Form $form, array $values): ?Contact
    {
        $attributes = [];

        foreach ($form->fields as $field) {
            $contactAttribute = $field->type->contactAttribute();
            $value = $values[$field->slug] ?? null;

            if ($contactAttribute !== null && filled($value)) {
                $attributes[$contactAttribute] = $value;
            }
        }

        $email = $attributes['email'] ?? null;

        if (! is_string($email)) {
            return null;
        }

        // Only the consent questions this form asked are updated
        $attributes = [...$attributes, ...$this->marketingAnswers($form, $values)];

        $contact = Contact::withTrashed()->where('email', $email)->first();

        if ($contact === null) {
            return Contact::create($attributes);
        }

        if ($contact->trashed()) {
            $contact->restore();
        }

        $contact->update($attributes);

        return $contact;
    }

    /**
     * The answers to the marketing consent questions the form asked, unanswered meaning no
     *
     * @param  array<string, mixed>  $values
     * @return array<string, bool>
     */
    protected function marketingAnswers(Form $form, array $values): array
    {
        return collect(Form::MARKETING_FIELDS)
            ->filter(fn (string $key) => $form->getAttribute($key))
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($values[$key] ?? false)])
            ->all();
    }

    protected function answerValue(mixed $value): string|int|float|null
    {
        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
