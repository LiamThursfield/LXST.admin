<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use App\Jobs\Bookings\SendBookingMessage;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use App\Models\Settings\MessagingSettings;
use App\Services\Industry\Terminology;

/**
 * Tells customers about their bookings by the channel the booking asks for, falling back when it
 * can't be used: WhatsApp without an approved template or a WhatsApp number goes by text, a text
 * without a number or a provider goes by email, and with no email address nothing is sent. Every
 * message is logged, and sent from the queue.
 */
class BookingMessenger
{
    public function __construct(
        protected BookingMessages $messages,
        protected MessagingSettings $settings,
        protected Terminology $terminology,
    ) {}

    public function send(Booking $booking, MessageEvent $event): ?MessageLog
    {
        $wording = $this->messages->wordingFor($event);

        if (! $wording['is_enabled']) {
            return null;
        }

        return $this->queue($booking, $event, $wording['whatsapp_template_sid']);
    }

    /**
     * A one-off message written by staff, e.g. "your table is ready". WhatsApp only allows a
     * business to start with an approved template, so it goes by text instead.
     */
    public function sendCustom(Booking $booking, string $body): MessageLog
    {
        return $this->queue($booking, MessageEvent::Custom, null, $body);
    }

    protected function queue(Booking $booking, MessageEvent $event, ?string $whatsAppTemplate, ?string $body = null): MessageLog
    {
        [$channel, $notes] = $this->channelFor($booking, $whatsAppTemplate);

        $recipient = $channel === MessageChannel::Email ? $booking->customer_email : $this->messages->phoneFor($booking);

        if (blank($recipient)) {
            return MessageLog::query()->create([
                'booking_id' => $booking->id,
                'event' => $event,
                'channel' => $channel,
                'body' => $body,
                'status' => MessageStatus::Skipped,
                'note' => implode(' ', [...$notes, __('bookings.messages.log.no_contact_details')]),
            ]);
        }

        $log = MessageLog::query()->create([
            'booking_id' => $booking->id,
            'event' => $event,
            'channel' => $channel,
            'recipient' => $recipient,
            'body' => $body,
            'status' => MessageStatus::Queued,
            'note' => $notes === [] ? null : implode(' ', $notes),
        ]);

        SendBookingMessage::dispatch($log->id)->afterCommit();

        return $log;
    }

    /**
     * The channel to use, and why it isn't the one asked for, if it isn't
     *
     * @return array{0: MessageChannel, 1: list<string>}
     */
    protected function channelFor(Booking $booking, ?string $whatsAppTemplate): array
    {
        $channel = $booking->channel;
        $contact = $booking->contact;
        $hasPhone = $this->messages->phoneFor($booking) !== null;
        $notes = [];

        if ($channel === MessageChannel::WhatsApp) {
            $reason = match (true) {
                $contact->whatsapp_opted_out ?? false => 'opted_out',
                ! $this->settings->canSendWhatsApp() => 'not_set_up',
                blank($whatsAppTemplate) => 'no_template',
                ! $hasPhone => 'no_number',
                default => null,
            };

            if ($reason === null) {
                return [$channel, $notes];
            }

            $notes[] = __("bookings.messages.log.whatsapp_{$reason}");
            $channel = MessageChannel::Sms;
        }

        if ($channel === MessageChannel::Sms) {
            $reason = match (true) {
                $contact->sms_opted_out ?? false => 'opted_out',
                ! $this->settings->canSendSms() => 'not_set_up',
                ! $hasPhone => 'no_number',
                default => null,
            };

            if ($reason === null) {
                return [$channel, $notes];
            }

            $notes[] = __("bookings.messages.log.sms_{$reason}");
        }

        return [MessageChannel::Email, $notes];
    }
}
