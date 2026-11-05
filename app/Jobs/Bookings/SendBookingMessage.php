<?php

namespace App\Jobs\Bookings;

use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageStatus;
use App\Mail\Bookings\BookingMessageMail;
use App\Models\Bookings\MessageLog;
use App\Services\Bookings\BookingMessages;
use App\Services\Messaging\TwilioClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one logged booking message, and records whether it went. A failure is logged with its
 * reason rather than retried, so a customer is never sent the same message twice.
 */
class SendBookingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $logId) {}

    public function handle(BookingMessages $messages, TwilioClient $twilio): void
    {
        $log = MessageLog::query()->with('booking.location')->find($this->logId);
        $booking = $log?->booking;

        if ($log === null || $log->status !== MessageStatus::Queued || $booking === null || $log->recipient === null) {
            return;
        }

        $wording = $messages->wordingFor($log->event);

        try {
            $providerId = match ($log->channel) {
                MessageChannel::Email => $this->sendEmail($log->recipient, $messages->fill($wording['email_subject'], $booking), $messages->fill($wording['email_body'], $booking)),
                MessageChannel::Sms => $twilio->sendSms($log->recipient, $messages->fill($wording['sms_body'], $booking)),
                MessageChannel::WhatsApp => $twilio->sendWhatsApp($log->recipient, (string) $wording['whatsapp_template_sid'], $messages->numberedValues($booking)),
            };
        } catch (Throwable $exception) {
            report($exception);

            $log->update([
                'status' => MessageStatus::Failed,
                'note' => trim(($log->note ?? '').' '.Str::limit($exception->getMessage(), 500)),
            ]);

            return;
        }

        $log->update([
            'status' => MessageStatus::Sent,
            'provider_message_id' => $providerId,
            'sent_at' => now(),
        ]);
    }

    protected function sendEmail(string $to, string $subject, string $body): ?string
    {
        Mail::to($to)->send(new BookingMessageMail($subject, $body));

        return null;
    }
}
