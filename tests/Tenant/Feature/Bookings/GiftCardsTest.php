<?php

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\TenantModule;
use App\Mail\Bookings\GiftCardMail;
use App\Mail\Bookings\GiftCardPaymentMail;
use App\Models\Bookings\Booking;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use App\Models\Settings\BookingsSettings;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

beforeEach(function () {
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_test']);
    $this->actingAs($this->createUserWithPermissions([...BookingsPermission::cases(), ...SettingsPermission::cases()]));
});

/**
 * The tenant's Stripe account, connected and able to take payments, with a 5% platform fee
 */
function connectStripeForGiftCards(): void
{
    tenant()->update(['stripe_account_id' => 'acct_test123', 'stripe_charges_enabled' => true, 'stripe_details_submitted' => true, 'platform_fee_percent' => 5]);
}

/**
 * Stripe making a checkout page, which returns the given session
 */
function expectGiftCardCheckout(string $sessionId = 'cs_test_gift', ?Closure $withArgs = null): void
{
    // Partial, so webhooks are still checked as Stripe signs them
    test()->partialMock(StripeGateway::class, function (MockInterface $mock) use ($sessionId, $withArgs) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $expectation = $mock->shouldReceive('createCheckout')->once()->andReturn(['id' => $sessionId, 'url' => "https://checkout.stripe.com/c/pay/{$sessionId}"]);

        if ($withArgs !== null) {
            $expectation->withArgs($withArgs);
        }
    });
}

/**
 * A signed webhook, as Stripe sends it
 *
 * @param  array<string, mixed>  $object
 */
function giftCardWebhook(string $type, array $object): TestResponse
{
    $payload = (string) json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => $type, 'account' => 'acct_test123', 'data' => ['object' => $object]]);
    $time = time();
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_test');

    return test()->call('POST', route('central.webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload);
}

describe('issuing', function () {
    test('a card sold in person is ready straight away, with a code, and emailed to whoever it\'s for', function () {
        $this->post(route('admin.bookings.gift-cards.store'), [
            'kind' => 'amount',
            'amount' => '50',
            'method' => 'cash',
            'purchaser_name' => 'Chris Dale',
            'recipient_name' => 'Sam Dale',
            'recipient_email' => 'sam@example.test',
            'message' => 'Happy birthday',
            'expires_on' => today()->addYear()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $card = GiftCard::query()->sole();

        expect($card->only(['kind', 'status', 'amount', 'balance', 'price_paid', 'payment_method']))
            ->toBe(['kind' => GiftCardKind::Amount, 'status' => GiftCardStatus::Active, 'amount' => 5000, 'balance' => 5000, 'price_paid' => 5000, 'payment_method' => PaymentMethod::Cash])
            ->and($card->code)->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/')
            ->and($card->sent_at)->not->toBeNull();

        Mail::assertQueued(GiftCardMail::class, fn (GiftCardMail $mail) => $mail->hasTo('sam@example.test') && $mail->code === $card->code && $mail->value === '£50.00');
    });

    test('a voucher is worth its service\'s price, and one given free has nothing paid', function () {
        $service = Service::factory()->create(['name' => 'Massage', 'price' => 4500]);

        $this->post(route('admin.bookings.gift-cards.store'), ['kind' => 'service', 'service_id' => $service->id, 'method' => 'free'])->assertSessionHas('success');

        expect(GiftCard::query()->sole()->only(['kind', 'service_id', 'amount', 'price_paid', 'payment_method']))
            ->toBe(['kind' => GiftCardKind::Service, 'service_id' => $service->id, 'amount' => 4500, 'price_paid' => 0, 'payment_method' => null]);

        // No email to send it to
        Mail::assertNothingQueued();
    });

    test('a service without a set price can\'t be a voucher', function () {
        $service = Service::factory()->create(['price_type' => 'on_quote', 'price' => null]);

        $this->post(route('admin.bookings.gift-cards.store'), ['kind' => 'service', 'service_id' => $service->id, 'method' => 'card'])
            ->assertSessionHasErrors('service_id');
    });

    test('the form suggests the business\'s usual expiry, and only offers selling online with Stripe', function () {
        app(BookingsSettings::class)->fill(['gift_card_months' => 6])->save();

        $this->get(route('admin.bookings.gift-cards.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaultExpiry', today()->addMonthsNoOverflow(6)->toDateString())
                ->where('methods', fn ($methods) => ! collect($methods)->contains('value', 'online')));

        connectStripeForGiftCards();

        $this->get(route('admin.bookings.gift-cards.create'))
            ->assertInertia(fn (Assert $page) => $page->where('methods', fn ($methods) => collect($methods)->contains('value', 'online')));
    });

    test('selling online makes a checkout with the platform fee and emails the buyer the link; the card is sent once paid', function () {
        connectStripeForGiftCards();
        expectGiftCardCheckout(withArgs: fn (string $account, int $amount, string $currency, string $description, int $fee, ?string $email, string $success, string $cancel, array $metadata) => $account === 'acct_test123' && $amount === 4000 && $fee === 200 && $email === 'buyer@example.test' && isset($metadata['gift_card_id']));

        $this->post(route('admin.bookings.gift-cards.store'), ['kind' => 'amount', 'amount' => '40', 'method' => 'online', 'purchaser_email' => 'buyer@example.test', 'recipient_email' => 'friend@example.test'])
            ->assertSessionHas('success');

        $card = GiftCard::query()->sole();

        expect($card->status)->toBe(GiftCardStatus::Pending)
            ->and($card->application_fee)->toBe(200);

        Mail::assertQueued(GiftCardPaymentMail::class, fn (GiftCardPaymentMail $mail) => $mail->hasTo('buyer@example.test') && $mail->link === route('bookings.gift-cards.show', $card->public_id));
        Mail::assertNotQueued(GiftCardMail::class);

        giftCardWebhook('checkout.session.completed', ['id' => 'cs_test_gift', 'mode' => 'payment', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'metadata' => ['gift_card_id' => (string) $card->id]])->assertOk();
        // Heard twice, as Stripe may send it again
        giftCardWebhook('checkout.session.completed', ['id' => 'cs_test_gift', 'mode' => 'payment', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'metadata' => ['gift_card_id' => (string) $card->id]])->assertOk();

        expect($card->fresh()->status)->toBe(GiftCardStatus::Active)
            ->and($card->fresh()->stripe_payment_intent_id)->toBe('pi_1')
            ->and(Payment::query()->count())->toBe(0);

        Mail::assertQueued(GiftCardMail::class, 1);
        Mail::assertQueued(GiftCardMail::class, fn (GiftCardMail $mail) => $mail->hasTo('friend@example.test'));
    });

    test('a card whose link runs out unpaid is never usable', function () {
        connectStripeForGiftCards();
        $card = GiftCard::factory()->pending()->create();

        giftCardWebhook('checkout.session.expired', ['id' => $card->stripe_checkout_session_id, 'mode' => 'payment', 'metadata' => ['gift_card_id' => (string) $card->id]])->assertOk();

        expect($card->fresh()->status)->toBe(GiftCardStatus::Expired);
    });

    test('it can be emailed again and cancelled', function () {
        $card = GiftCard::factory()->create(['recipient_email' => 'sam@example.test']);

        $this->post(route('admin.bookings.gift-cards.send', $card))->assertSessionHas('success');
        Mail::assertQueued(GiftCardMail::class, fn (GiftCardMail $mail) => $mail->hasTo('sam@example.test'));

        $this->post(route('admin.bookings.gift-cards.cancel', $card))->assertSessionHas('success');
        expect($card->fresh()->status)->toBe(GiftCardStatus::Cancelled);

        $this->post(route('admin.bookings.gift-cards.cancel', $card))->assertSessionHasErrors('card');
    });
});

describe('the list and a card', function () {
    test('the list counts cards past their date as expired, and finds cards by code or name', function () {
        GiftCard::factory()->create(['code' => 'AAAA-BBBB-CCCC', 'recipient_name' => 'Sam Dale']);
        GiftCard::factory()->expired()->create(['code' => 'DDDD-EEEE-FFFF']);

        $this->get(route('admin.bookings.gift-cards.index', ['filter' => ['status' => 'expired']]))
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/gift-card/Index')
                ->has('giftCards.data', 1)
                ->where('giftCards.data.0.code', 'DDDD-EEEE-FFFF')
                ->where('giftCards.data.0.status_label', 'Expired'));

        $this->get(route('admin.bookings.gift-cards.index', ['filter' => ['status' => 'active']]))
            ->assertInertia(fn (Assert $page) => $page->has('giftCards.data', 1)->where('giftCards.data.0.code', 'AAAA-BBBB-CCCC'));

        $this->get(route('admin.bookings.gift-cards.index', ['filter' => ['search' => 'aaaabbbb']]))
            ->assertInertia(fn (Assert $page) => $page->has('giftCards.data', 1));

        $this->get(route('admin.bookings.gift-cards.index', ['filter' => ['search' => 'Sam']]))
            ->assertInertia(fn (Assert $page) => $page->has('giftCards.data', 1));
    });

    test('a card shows where it\'s been used', function () {
        $card = GiftCard::factory()->worth(5000, 3000)->create();
        $booking = Booking::factory()->create(['customer_name' => 'Morgan Reed']);
        Payment::factory()->for($booking)->fromGiftCard($card)->create(['amount' => 2000]);

        $this->get(route('admin.bookings.gift-cards.show', $card))
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/gift-card/Show')
                ->where('giftCard.balance', '£30.00')
                ->where('giftCard.uses.0.customer_name', 'Morgan Reed')
                ->where('giftCard.uses.0.amount', '£20.00'));
    });

    test('they need the payment permissions and the bookings module', function () {
        $card = GiftCard::factory()->create();

        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

        $this->get(route('admin.bookings.gift-cards.index'))->assertForbidden();
        $this->get(route('admin.bookings.gift-cards.show', $card))->assertForbidden();
        $this->post(route('admin.bookings.gift-cards.store'), ['kind' => 'amount', 'amount' => '10', 'method' => 'cash'])->assertForbidden();

        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewPayments]));

        $this->get(route('admin.bookings.gift-cards.index'))->assertOk();
        $this->post(route('admin.bookings.gift-cards.cancel', $card))->assertForbidden();
        $this->post(route('admin.bookings.bookings.gift-card-payments.store', Booking::factory()->create()), ['code' => $card->code])->assertForbidden();

        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.bookings.gift-cards.index'))->assertNotFound();
        $this->get(route('bookings.gift-cards.show', $card->public_id))->assertNotFound();
    });
});

describe('using one on a booking', function () {
    test('a card pays as much as it can, however the code is typed, and is used up when empty', function () {
        $card = GiftCard::factory()->worth(3000)->create(['code' => 'ABCD-EFGH-JKLM']);
        $first = Booking::factory()->create(['price' => 2000]);
        $second = Booking::factory()->create(['price' => 5000]);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $first), ['code' => 'abcd efgh jklm'])->assertSessionHas('success');

        expect($first->payments()->sole()->only(['amount', 'method', 'status', 'gift_card_id']))
            ->toBe(['amount' => 2000, 'method' => PaymentMethod::GiftCard, 'status' => PaymentStatus::Paid, 'gift_card_id' => $card->id])
            ->and($card->fresh()->balance)->toBe(1000);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $second), ['code' => 'ABCD-EFGH-JKLM', 'amount' => '20'])
            ->assertSessionHasErrors(['amount' => 'Only £10.00 is left on this gift card.']);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $second), ['code' => 'ABCD-EFGH-JKLM'])->assertSessionHas('success');

        expect($second->payments()->sole()->amount)->toBe(1000)
            ->and($card->fresh()->only(['balance', 'status']))->toBe(['balance' => 0, 'status' => GiftCardStatus::Used]);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $second), ['code' => 'ABCD-EFGH-JKLM'])
            ->assertSessionHasErrors(['code' => 'This gift card has been used.']);
    });

    test('a voucher pays what\'s left in full, only for its service', function () {
        $service = Service::factory()->create(['name' => 'Massage', 'price' => 4500]);
        $card = GiftCard::factory()->forService($service)->create();
        $other = Booking::factory()->create(['price' => 4500]);
        $booking = Booking::factory()->create(['service_id' => $service->id, 'service_name' => 'Massage', 'price' => 5500]);
        Payment::factory()->for($booking)->inPerson()->create(['amount' => 1000]);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $other), ['code' => $card->code])
            ->assertSessionHasErrors(['code' => 'This voucher is for Massage.']);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $booking), ['code' => $card->code])->assertSessionHas('success');

        expect($booking->payments()->where('method', PaymentMethod::GiftCard)->sole()->amount)->toBe(4500)
            ->and($card->fresh()->status)->toBe(GiftCardStatus::Used);
    });

    test('codes that don\'t exist, past their date, cancelled or not paid for are refused', function (Closure $makeCard, string $message) {
        $card = $makeCard();
        $booking = Booking::factory()->create(['price' => 2000]);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $booking), ['code' => $card?->code ?? 'NOPE-NOPE-NOPE'])
            ->assertSessionHasErrors(['code' => $message]);

        expect($booking->payments()->count())->toBe(0);
    })->with([
        'unknown' => [fn () => null, 'No gift card has that code.'],
        'expired' => [fn () => GiftCard::factory()->create(['expires_on' => '2026-01-31']), 'This gift card expired on 31 Jan 2026.'],
        'cancelled' => [fn () => GiftCard::factory()->cancelled()->create(), 'This gift card has been cancelled.'],
        'unpaid' => [fn () => GiftCard::factory()->pending()->create(), 'This gift card hasn\'t been paid for.'],
    ]);

    test('nothing is taken from a card when nothing is owed', function () {
        $card = GiftCard::factory()->create();
        $booking = Booking::factory()->create(['price' => 2000]);
        Payment::factory()->for($booking)->inPerson()->create(['amount' => 2000]);

        $this->post(route('admin.bookings.bookings.gift-card-payments.store', $booking), ['code' => $card->code])
            ->assertSessionHasErrors(['code' => 'There\'s nothing left to pay.']);
    });

    test('refunding a payment puts it back on the card; a voucher\'s only whole', function () {
        $card = GiftCard::factory()->worth(5000, 0)->used()->create();
        $payment = Payment::factory()->for(Booking::factory()->create(['price' => 5000]))->fromGiftCard($card)->create(['amount' => 5000]);

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '15'])->assertSessionHas('success');

        expect($card->fresh()->only(['balance', 'status']))->toBe(['balance' => 1500, 'status' => GiftCardStatus::Active]);

        $service = Service::factory()->create(['price' => 4500]);
        $voucher = GiftCard::factory()->forService($service)->used()->create();
        $voucherPayment = Payment::factory()->for(Booking::factory()->create(['service_id' => $service->id, 'price' => 4500]))->fromGiftCard($voucher)->create(['amount' => 4500]);

        $this->post(route('admin.bookings.payments.refunds.store', $voucherPayment), ['amount' => '10'])
            ->assertSessionHasErrors(['amount' => 'A voucher\'s payment can only be given back whole: £45.00.']);

        $this->post(route('admin.bookings.payments.refunds.store', $voucherPayment), ['amount' => '45'])->assertSessionHas('success');

        expect($voucher->fresh()->only(['balance', 'status']))->toBe(['balance' => 4500, 'status' => GiftCardStatus::Active]);
    });
});

describe('buying online', function () {
    test('the page is only there when switched on and Stripe is connected', function () {
        $this->get(route('bookings.gift-cards.shop'))->assertNotFound();

        app(BookingsSettings::class)->fill(['gift_cards_online' => true])->save();
        $this->get(route('bookings.gift-cards.shop'))->assertNotFound();

        connectStripeForGiftCards();
        Service::factory()->create(['name' => 'Massage', 'price' => 4500]);
        Service::factory()->create(['name' => 'Staff only', 'price' => 3000, 'is_bookable_online' => false]);

        $this->get(route('bookings.gift-cards.shop'))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/GiftCardShop')
                ->has('kinds', 2)
                ->has('services', 1)
                ->where('services.0.label', 'Massage (£45.00)'));
    });

    test('buying one sends the customer to Stripe, with the business\'s usual expiry', function () {
        app(BookingsSettings::class)->fill(['gift_cards_online' => true, 'gift_card_months' => 12])->save();
        connectStripeForGiftCards();
        expectGiftCardCheckout('cs_test_shop');

        $this->post(route('bookings.gift-cards.buy'), [
            'kind' => 'amount',
            'amount' => '25',
            'purchaser_name' => 'Chris Dale',
            'purchaser_email' => 'chris@example.test',
        ])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_shop');

        expect(GiftCard::query()->sole()->only(['status', 'amount', 'purchaser_email']))
            ->toBe(['status' => GiftCardStatus::Pending, 'amount' => 2500, 'purchaser_email' => 'chris@example.test'])
            ->and(GiftCard::query()->sole()->expires_on->toDateString())->toBe(today()->addMonthsNoOverflow(12)->toDateString());
    });

    test('amounts outside the limits, and services not booked online, are refused', function () {
        app(BookingsSettings::class)->fill(['gift_cards_online' => true])->save();
        connectStripeForGiftCards();
        $staffOnly = Service::factory()->create(['is_bookable_online' => false]);

        $this->post(route('bookings.gift-cards.buy'), ['kind' => 'amount', 'amount' => '2', 'purchaser_name' => 'A', 'purchaser_email' => 'a@example.test'])
            ->assertSessionHasErrors('amount');
        $this->post(route('bookings.gift-cards.buy'), ['kind' => 'service', 'service_id' => $staffOnly->id, 'purchaser_name' => 'A', 'purchaser_email' => 'a@example.test'])
            ->assertSessionHasErrors('service_id');

        expect(GiftCard::query()->count())->toBe(0);
    });

    test('the card\'s page sends the buyer to pay, and shows the code only once paid', function () {
        $pending = GiftCard::factory()->pending()->create();

        $this->get(route('bookings.gift-cards.show', $pending->public_id))->assertRedirect((string) $pending->checkout_url);
        $this->get(route('bookings.gift-cards.show', ['publicId' => $pending->public_id, 'back' => 1]))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/GiftCard')->where('code', null));

        $card = GiftCard::factory()->worth(5000, 2000)->create();

        $this->get(route('bookings.gift-cards.show', $card->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('code', $card->code)->where('balance', '£20.00')->where('value', '£50.00'));
    });

    test('the settings save how long cards last and whether they\'re sold online', function () {
        $this->put(route('admin.site-settings.payments.update'), ['gift_card_months' => '', 'gift_cards_online' => true, 'tax_label' => 'VAT'])->assertSessionHas('success');

        $settings = app(BookingsSettings::class);
        $settings->refresh();

        expect($settings->gift_card_months)->toBeNull()
            ->and($settings->gift_cards_online)->toBeTrue();

        $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));
        $this->put(route('admin.site-settings.payments.update'), ['gift_card_months' => 12, 'gift_cards_online' => false, 'tax_label' => 'VAT'])->assertForbidden();
    });
});
