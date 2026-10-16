<?php

namespace App\Mail\Settings;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from the mail settings page to check the tenant's mailer works
 */
class MailSettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $siteName, public string $mailerName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('site_settings.mail.test_mail.subject', ['site' => $this->siteName]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.settings.test');
    }
}
