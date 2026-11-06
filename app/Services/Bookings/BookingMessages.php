<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\MessageEvent;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageTemplate;
use App\Models\Settings\GeneralSettings;
use App\Services\Industry\Terminology;
use App\Services\Messaging\PhoneNumber;

/**
 * The wording of booking messages: a tenant's own, or the default in the tenant's words, filled in
 * with the booking's details. Placeholders are in braces, e.g. {customer_name}.
 *
 * @phpstan-type Wording array{is_enabled: bool, offset_hours: int|null, email_subject: string, email_body: string, sms_body: string, whatsapp_template_sid: string|null}
 */
class BookingMessages
{
    /**
     * The placeholders a message can use
     *
     * @var list<string>
     */
    public const array PLACEHOLDERS = ['customer_name', 'service', 'date', 'time', 'location', 'business'];

    public function __construct(
        protected Terminology $terminology,
        protected GeneralSettings $general,
    ) {}

    /**
     * The wording for an event, with blank parts taken from the default
     *
     * @return Wording
     */
    public function wordingFor(MessageEvent $event): array
    {
        $template = MessageTemplate::query()->firstWhere('event', $event->value);
        $defaults = $this->defaultsFor($event);

        return [
            'is_enabled' => $template->is_enabled ?? $event->isEnabledByDefault(),
            'offset_hours' => $template->offset_hours ?? $event->defaultOffsetHours(),
            'email_subject' => filled($template?->email_subject) ? (string) $template->email_subject : $defaults['email_subject'],
            'email_body' => filled($template?->email_body) ? (string) $template->email_body : $defaults['email_body'],
            'sms_body' => filled($template?->sms_body) ? (string) $template->sms_body : $defaults['sms_body'],
            'whatsapp_template_sid' => $template?->whatsapp_template_sid,
        ];
    }

    /**
     * The default wording, in the tenant's words
     *
     * @return array{email_subject: string, email_body: string, sms_body: string}
     */
    public function defaultsFor(MessageEvent $event): array
    {
        return [
            'email_subject' => $this->terminology->translate("bookings.messages.defaults.{$event->value}.email_subject"),
            'email_body' => $this->terminology->translate("bookings.messages.defaults.{$event->value}.email_body"),
            'sms_body' => $this->terminology->translate("bookings.messages.defaults.{$event->value}.sms_body"),
        ];
    }

    /**
     * The email subject of a one-off message
     */
    public function customSubject(): string
    {
        return $this->terminology->translate('bookings.messages.custom.subject', ['business' => $this->general->site_name]);
    }

    /**
     * Fills in a message's placeholders from the booking. Unknown placeholders are left as they are.
     */
    public function fill(string $text, Booking $booking): string
    {
        $values = $this->values($booking);

        return (string) preg_replace_callback('/\{(\w+)\}/', fn (array $match) => $values[$match[1]] ?? $match[0], $text);
    }

    /**
     * The values for a WhatsApp template, numbered as PLACEHOLDERS lists them: {{1}} is the
     * customer's name, {{2}} the service, and so on
     *
     * @return array<int, string>
     */
    public function numberedValues(Booking $booking): array
    {
        $values = $this->values($booking);
        $numbered = [];

        foreach (self::PLACEHOLDERS as $index => $placeholder) {
            $numbered[$index + 1] = $values[$placeholder];
        }

        return $numbered;
    }

    /**
     * @return array<string, string>
     */
    protected function values(Booking $booking): array
    {
        $start = $booking->starts_at?->copy()->setTimezone($booking->timezone);

        return [
            'customer_name' => $booking->customer_name,
            'service' => $booking->service_name,
            'date' => $start?->format('l j F Y') ?? $this->terminology->translate('bookings.messages.no_time_yet'),
            'time' => $start?->format('H:i') ?? '',
            'location' => $booking->location->name ?? $this->general->site_name,
            'business' => $this->general->site_name,
        ];
    }

    /**
     * The customer's number in international format, if they have a usable one
     */
    public function phoneFor(Booking $booking): ?string
    {
        return PhoneNumber::international($booking->customer_phone);
    }
}
