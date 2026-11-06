<?php

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use App\Mail\Bookings\BookingMessageMail;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use App\Models\Bookings\MessageTemplate;
use App\Models\Settings\MessagingSettings;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\ScheduledBookingMessages;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-02 12:00:00');
    Mail::fake();
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);
    Http::preventStrayRequests();
});

function bookingAt(string $start, array $attributes = []): Booking
{
    return Booking::factory()->create([
        'starts_at' => $start,
        'ends_at' => CarbonImmutable::parse($start)->addHour(),
        'customer_email' => 'morgan@example.test',
        'created_at' => '2026-10-01 09:00:00',
        ...$attributes,
    ]);
}

function sendDue(): int
{
    return app(ScheduledBookingMessages::class)->sendDue();
}

describe('reminders', function () {
    test('one goes a day before a confirmed booking, once', function () {
        $soon = bookingAt('2026-11-03 10:00:00');
        bookingAt('2026-11-04 10:00:00');
        bookingAt('2026-11-03 11:00:00', ['status' => BookingStatus::Cancelled]);
        bookingAt('2026-11-02 11:00:00');

        expect(sendDue())->toBe(1)
            ->and($soon->messages()->sole()->event)->toBe(MessageEvent::Reminder)
            ->and(sendDue())->toBe(0);

        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => str_starts_with($mail->subjectLine, 'Reminder: your booking on Tuesday 3 November 2026'));
    });

    test('a booking made inside the reminder time gets none, as its confirmation has just gone', function () {
        bookingAt('2026-11-03 10:00:00', ['created_at' => '2026-11-02 11:00:00']);

        expect(sendDue())->toBe(0);
    });

    test('the hours can be changed, and reminders turned off', function () {
        $booking = bookingAt('2026-11-04 10:00:00');
        MessageTemplate::factory()->create(['event' => MessageEvent::Reminder, 'offset_hours' => 48, 'email_subject' => null, 'email_body' => null, 'sms_body' => null]);

        expect(sendDue())->toBe(1);

        MessageTemplate::query()->update(['is_enabled' => false]);
        bookingAt('2026-11-04 11:00:00');

        expect(sendDue())->toBe(0)
            ->and($booking->messages()->count())->toBe(1);
    });

    test('a reminder that couldn\'t be sent isn\'t tried again', function () {
        $booking = bookingAt('2026-11-03 10:00:00', ['customer_email' => null]);

        sendDue();
        sendDue();

        expect($booking->messages()->sole()->status)->toBe(MessageStatus::Skipped);
    });
});

describe('follow-ups and review requests', function () {
    test('they\'re off until turned on, then go after a completed booking ends', function () {
        $done = bookingAt('2026-11-02 08:00:00', ['status' => BookingStatus::Completed]);
        bookingAt('2026-11-02 08:00:00', ['status' => BookingStatus::NoShow]);
        bookingAt('2026-11-02 11:00:00', ['status' => BookingStatus::Completed]);
        bookingAt('2026-10-20 08:00:00', ['status' => BookingStatus::Completed]);

        expect(sendDue())->toBe(0);

        MessageTemplate::factory()->create(['event' => MessageEvent::FollowUp, 'email_subject' => null, 'email_body' => null, 'sms_body' => null]);
        MessageTemplate::factory()->create(['event' => MessageEvent::ReviewRequest, 'offset_hours' => 2, 'email_subject' => null, 'email_body' => null, 'sms_body' => null]);

        expect(sendDue())->toBe(2)
            ->and($done->messages()->pluck('event')->map->value->sort()->values()->all())->toBe(['follow_up', 'review_request']);
    });
});

describe('one-off messages', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('staff can send one, filled in, and it\'s logged with what was said', function () {
        $booking = bookingAt('2026-11-02 13:00:00', ['customer_name' => 'Morgan']);

        $this->post(route('admin.bookings.bookings.message', $booking), ['body' => 'Hi {customer_name}, we\'re ready for you now.'])
            ->assertSessionHas('success');

        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => $mail->body === 'Hi Morgan, we\'re ready for you now.');

        expect($booking->messages()->sole()->only(['event', 'body', 'status']))
            ->toBe(['event' => MessageEvent::Custom, 'body' => 'Hi {customer_name}, we\'re ready for you now.', 'status' => MessageStatus::Sent]);
    });

    test('one asked for by WhatsApp goes by text, as WhatsApp needs a template', function () {
        $settings = app(MessagingSettings::class);
        $settings->fill(['provider' => 'twilio', 'twilio_account_sid' => 'AC'.str_repeat('0f', 16), 'twilio_auth_token' => 'token', 'sms_from' => '+447700900000', 'whatsapp_from' => '+447700900001']);
        $settings->save();
        $booking = bookingAt('2026-11-02 13:00:00', ['channel' => MessageChannel::WhatsApp, 'customer_phone' => '+447700900123']);

        $this->post(route('admin.bookings.bookings.message', $booking), ['body' => 'On my way'])->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => $request['To'] === '+447700900123' && $request['Body'] === 'On my way');
        expect($booking->messages()->sole()->channel)->toBe(MessageChannel::Sms);
    });

    test('with nothing to send to, staff are told', function () {
        $booking = bookingAt('2026-11-02 13:00:00', ['customer_email' => null]);

        $this->post(route('admin.bookings.bookings.message', $booking), ['body' => 'Hello'])->assertSessionHas('error');
    });

    test('it needs the manage permission, and some words', function () {
        $booking = bookingAt('2026-11-02 13:00:00');

        $this->post(route('admin.bookings.bookings.message', $booking), ['body' => ''])->assertSessionHasErrors('body');

        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        $this->post(route('admin.bookings.bookings.message', $booking), ['body' => 'Hello'])->assertForbidden();
    });

    test('the booking page offers ready-made messages, and custom ones have no template to edit', function () {
        $booking = bookingAt('2026-11-02 13:00:00');

        $this->get(route('admin.bookings.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->has('messagePresets', 3));

        $this->get(route('admin.bookings.messages.index'))
            ->assertInertia(fn (Assert $page) => $page->has('events', 6)->where('events.3.value', 'reminder')->where('events.3.offset_hours', 24)->where('events.4.is_enabled', false));
        $this->get(route('admin.bookings.messages.edit', 'custom'))->assertNotFound();
    });
});

test('the command sends due messages for tenants with the Bookings module', function () {
    bookingAt('2026-11-03 10:00:00');

    $this->artisan('bookings:send-scheduled-messages', ['--tenant' => tenant()->getTenantKey()])
        ->expectsOutputToContain('Sent 1 scheduled booking messages')
        ->assertSuccessful();

    expect(MessageLog::query()->count())->toBe(1);
});
