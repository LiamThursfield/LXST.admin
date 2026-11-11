<?php

namespace App\Mail\Bookings;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A link to pay for a gift card staff have set up, sent to the buyer
 */
class GiftCardPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $business,
        public string $value,
        public string $price,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('bookings.gift_cards.mail.payment_subject', ['business' => $this->business]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bookings.gift-card-payment');
    }
}
