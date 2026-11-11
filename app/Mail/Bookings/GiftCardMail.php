<?php

namespace App\Mail\Bookings;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A gift card or voucher, sent to whoever it's for. Everything is worked out when it's created, as
 * queued jobs don't know which tenant domain the request came in on.
 */
class GiftCardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $business,
        public string $value,
        public string $code,
        public ?string $recipientName,
        public ?string $purchaserName,
        public ?string $personalMessage,
        public ?string $expiresOn,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('bookings.gift_cards.mail.subject', ['business' => $this->business]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bookings.gift-card');
    }
}
