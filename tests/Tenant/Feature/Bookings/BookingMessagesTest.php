<?php

use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use App\Enums\TenantModule;
use App\Mail\Bookings\BookingMessageMail;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use App\Models\Bookings\MessageTemplate;
use App\Models\Bookings\Service;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\Contact;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\MessagingSettings;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Bookings\BookingMessenger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A made-up account SID, put together at runtime so it isn't mistaken for a real one in the code
 */
function twilioSid(): string
{
    return 'AC'.str_repeat('0f', 16);
}

beforeEach(function () {
    $this->travelTo('2026-11-02 08:00:00');
    Mail::fake();
    // A test can set its own reply from Twilio here
    $this->twilioReply = Http::response(['sid' => 'SM123'], 201);
    Http::fake(['api.twilio.com/*' => fn () => $this->twilioReply]);
    Http::preventStrayRequests();

    $this->actingAs($this->createUserWithPermissions([...BookingsPermission::cases(), ...SettingsPermission::cases()]));

    $settings = app(GeneralSettings::class);
    $settings->site_name = 'Northside';
    $settings->save();

    $this->service = Service::factory()->create(['name' => 'Haircut', 'duration_minutes' => 60]);
    $person = BookableResource::factory()->create();
    $person->services()->attach($this->service);
    WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 1]);
});

function setUpTwilio(array $overrides = []): void
{
    $settings = app(MessagingSettings::class);
    $settings->fill([
        'provider' => MessagingSettings::PROVIDER_TWILIO,
        'twilio_account_sid' => twilioSid(),
        'twilio_auth_token' => 'secret-token',
        'sms_from' => '+447700900000',
        'whatsapp_from' => '+447700900001',
        ...$overrides,
    ]);
    $settings->save();
}

function bookHaircut(array $overrides = []): Booking
{
    test()->post(route('admin.bookings.bookings.store'), [
        'service_id' => test()->service->id,
        'date' => '2026-11-09',
        'time' => '10:00',
        'customer_name' => 'Morgan Reed',
        'customer_email' => 'morgan@example.test',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Booking::query()->latest('id')->firstOrFail();
}

describe('sending', function () {
    test('a new booking is confirmed by email, in the tenant\'s words, and logged', function () {
        tenant()->update(['industry' => 'beauty']);

        $booking = bookHaircut();

        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => $mail->hasTo('morgan@example.test')
            && $mail->subjectLine === 'Your appointment is confirmed'
            && str_contains($mail->body, 'Hi Morgan Reed')
            && str_contains($mail->body, 'Haircut is confirmed for Monday 9 November 2026 at 10:00'));

        $log = $booking->messages()->sole();

        expect($log->event)->toBe(MessageEvent::Booked)
            ->and($log->channel)->toBe(MessageChannel::Email)
            ->and($log->status)->toBe(MessageStatus::Sent)
            ->and($log->sent_at)->not->toBeNull();
    });

    test('a text goes through Twilio, filled in from the tenant\'s wording', function () {
        setUpTwilio();
        MessageTemplate::factory()->create(['sms_body' => 'Hi {customer_name}, see you {time}. {business}']);

        $booking = bookHaircut(['channel' => 'sms', 'customer_phone' => '+44 7700 900123']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.twilioSid().'/Messages.json'
            && $request['To'] === '+447700900123'
            && $request['From'] === '+447700900000'
            && $request['Body'] === 'Hi Morgan Reed, see you 10:00. Northside');

        expect($booking->messages()->sole()->only(['status', 'provider_message_id', 'recipient']))
            ->toBe(['status' => MessageStatus::Sent, 'provider_message_id' => 'SM123', 'recipient' => '+447700900123']);
    });

    test('WhatsApp uses the approved template, with the details numbered', function () {
        setUpTwilio();
        MessageTemplate::factory()->withWhatsApp()->create();

        bookHaircut(['channel' => 'whatsapp', 'customer_phone' => '+447700900123']);

        Http::assertSent(fn (Request $request) => $request['To'] === 'whatsapp:+447700900123'
            && $request['From'] === 'whatsapp:+447700900001'
            && $request['ContentSid'] === 'HX0123456789abcdef0123456789abcdef'
            && json_decode($request['ContentVariables'], true) === ['1' => 'Morgan Reed', '2' => 'Haircut', '3' => 'Monday 9 November 2026', '4' => '10:00', '5' => 'Northside', '6' => 'Northside']);
    });

    test('it falls back when a channel can\'t be used, and says why', function (array $settings, ?Closure $contact, string $wanted, MessageChannel $used, string $note) {
        setUpTwilio($settings);
        $contact?->__invoke();

        $booking = bookHaircut(['channel' => $wanted, 'customer_phone' => '+447700900123']);
        $log = $booking->messages()->sole();

        expect($log->channel)->toBe($used)
            ->and($log->note)->toContain($note);
    })->with([
        'WhatsApp without a template goes by text' => [[], null, 'whatsapp', MessageChannel::Sms, 'no approved WhatsApp template'],
        'WhatsApp opted out goes by text' => [[], fn () => Contact::factory()->create(['email' => 'morgan@example.test', 'whatsapp_opted_out' => true]), 'whatsapp', MessageChannel::Sms, 'opted out of WhatsApp'],
        'texts not set up go by email' => [['sms_from' => null], null, 'sms', MessageChannel::Email, 'Texts aren\'t set up'],
        'texts opted out go by email' => [[], fn () => Contact::factory()->create(['email' => 'morgan@example.test', 'sms_opted_out' => true]), 'sms', MessageChannel::Email, 'opted out of texts'],
    ]);

    test('with nothing to send to, it\'s logged as not sent', function () {
        $booking = bookHaircut(['customer_email' => null]);

        expect($booking->messages()->sole()->status)->toBe(MessageStatus::Skipped);
        Mail::assertNothingSent();
    });

    test('a failure from Twilio is logged with its reason, and not retried', function () {
        setUpTwilio();
        $this->twilioReply = Http::response(['message' => 'The To number is not valid.'], 400);

        $booking = bookHaircut(['channel' => 'sms', 'customer_phone' => '+447700900123']);
        $log = $booking->messages()->sole();

        expect($log->status)->toBe(MessageStatus::Failed)
            ->and($log->note)->toContain('The To number is not valid.');
    });

    test('a contact\'s preference is used when the booking doesn\'t choose', function () {
        setUpTwilio();
        Contact::factory()->create(['email' => 'morgan@example.test', 'preferred_channel' => MessageChannel::Sms]);

        $booking = bookHaircut(['customer_phone' => '+447700900123']);

        expect($booking->channel)->toBe(MessageChannel::Sms)
            ->and($booking->messages()->sole()->channel)->toBe(MessageChannel::Sms);
    });

    test('texts and WhatsApp need a number in international format', function () {
        $this->post(route('admin.bookings.bookings.store'), [
            'service_id' => $this->service->id,
            'date' => '2026-11-09',
            'time' => '10:00',
            'customer_name' => 'Morgan',
            'channel' => 'sms',
            'customer_phone' => '07700 900123',
        ])->assertSessionHasErrors(['customer_phone' => 'Use a number in international format, e.g. +447700900123.']);
    });

    test('moving and cancelling send their own messages, a walk-in nothing, and a turned off message nothing', function () {
        $booking = bookHaircut();

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '12:00'])->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'cancelled'])->assertSessionHasNoErrors();

        expect($booking->messages()->pluck('event')->map->value->sort()->values()->all())->toBe(['booked', 'cancelled', 'changed']);

        $this->travelTo('2026-11-09 10:00:00');
        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $this->service->id, 'walk_in' => true, 'customer_name' => 'Sam', 'customer_email' => 'sam@example.test'])
            ->assertSessionHasNoErrors();

        MessageTemplate::factory()->disabled()->create();
        app(BookingMessenger::class)->send($booking, MessageEvent::Booked);

        expect(MessageLog::query()->count())->toBe(3);
    });
});

describe('the admin', function () {
    test('messages can be reworded, turned off and given a WhatsApp template', function () {
        $this->get(route('admin.bookings.messages.edit', 'booked'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('defaults.email_subject', 'Your booking is confirmed'));

        $this->put(route('admin.bookings.messages.update', 'booked'), ['is_enabled' => false, 'sms_body' => 'Booked!', 'whatsapp_template_sid' => 'nope'])
            ->assertSessionHasErrors('whatsapp_template_sid');

        $this->put(route('admin.bookings.messages.update', 'booked'), ['is_enabled' => false, 'sms_body' => 'Booked!'])
            ->assertRedirect(route('admin.bookings.messages.index'));

        expect(MessageTemplate::query()->sole()->only(['is_enabled', 'sms_body']))->toBe(['is_enabled' => false, 'sms_body' => 'Booked!']);

        $this->get(route('admin.bookings.messages.edit', 'unknown'))->assertNotFound();
    });

    test('rewording needs its own permission, and the log only needs to see bookings', function () {
        MessageLog::factory()->failed()->create();
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

        $this->get(route('admin.bookings.messages.index'))->assertForbidden();
        $this->put(route('admin.bookings.messages.update', 'booked'), [])->assertForbidden();
        $this->get(route('admin.bookings.message-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.status', 'failed'));
    });

    test('the Twilio settings save, keeping a saved token, and a test text can be sent', function () {
        $data = ['provider' => 'twilio', 'twilio_account_sid' => twilioSid(), 'twilio_auth_token' => 'secret-token', 'sms_from' => 'Northside', 'whatsapp_from' => ''];

        $this->put(route('admin.site-settings.messaging.update'), [...$data, 'twilio_account_sid' => 'wrong'])->assertSessionHasErrors('twilio_account_sid');
        $this->put(route('admin.site-settings.messaging.update'), $data)->assertSessionHasNoErrors();
        $this->put(route('admin.site-settings.messaging.update'), [...$data, 'twilio_auth_token' => ''])->assertSessionHasNoErrors();

        $settings = app(MessagingSettings::class)->refresh();

        expect($settings->twilio_auth_token)->toBe('secret-token')
            ->and($settings->canSendSms())->toBeTrue();

        $this->get(route('admin.site-settings.messaging.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('hasToken', true)->missing('settings.twilio_auth_token'));

        $this->post(route('admin.site-settings.messaging.test'), ['to' => '+44 7700 900123'])->assertSessionHas('success');
        Http::assertSent(fn (Request $request) => $request['To'] === '+447700900123' && $request['From'] === 'Northside');
    });

    test('the settings need the Bookings module', function () {
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.site-settings.messaging.edit'))->assertNotFound();
    });

    test('a contact\'s channel and opt-outs are saved', function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
        $contact = Contact::factory()->create();

        $this->put(route('admin.crm.contacts.update', $contact), [
            'first_name' => 'Morgan',
            'last_name' => 'Reed',
            'email' => $contact->email,
            'preferred_channel' => 'whatsapp',
            'sms_opted_out' => true,
            'whatsapp_opted_out' => false,
            'addresses' => [],
        ])->assertRedirect(route('admin.crm.contacts.index'));

        expect($contact->fresh()->only(['preferred_channel', 'sms_opted_out']))->toBe(['preferred_channel' => MessageChannel::WhatsApp, 'sms_opted_out' => true]);
    });
});
