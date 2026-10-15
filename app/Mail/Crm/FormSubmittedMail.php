<?php

namespace App\Mail\Crm;

use App\Data\Crm\FormSubmissionAnswer;
use App\Models\Crm\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a form's notification recipients about a new (non-spam) submission
 */
class FormSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The link to the submission in the admin. Worked out when the mail is created, because
     * queued jobs don't know which tenant domain the request came in on.
     */
    public string $submissionUrl;

    public function __construct(public FormSubmission $submission)
    {
        $this->submissionUrl = route('admin.crm.form-submissions.show', $submission);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('crm.submissions.mail.subject', ['form' => $this->submission->form->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.crm.form-submitted',
            with: [
                'formName' => $this->submission->form->name,
                'submittedAt' => $this->submission->submitted_at->toDayDateTimeString(),
                'answers' => collect($this->submission->data->answers)
                    ->map(fn (FormSubmissionAnswer $answer) => [
                        'label' => $answer->label,
                        'value' => $answer->value === null || $answer->value === '' ? __('crm.submissions.show.no_answer') : (string) $answer->value,
                    ])
                    ->all(),
            ],
        );
    }
}
