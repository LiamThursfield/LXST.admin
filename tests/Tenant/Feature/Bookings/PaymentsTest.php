<?php

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\RefundStatus;
use App\Enums\Bookings\SavedCardStatus;
use App\Enums\TenantModule;
use App\Mail\Bookings\BookingMessageMail;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Refund;
use App\Models\Bookings\SavedCard;
use App\Models\Bookings\Service;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Bookings\BookingPayments;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Stripe\Exception\InvalidRequestException;

beforeEach(function () {
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_test']);
    $this->actingAs($this->createUserWithPermissions([...BookingsPermission::cases(), ...SettingsPermission::cases()]));
});

/**
 * The tenant's Stripe account, connected and able to take payments, with a platform fee
 */
function connectStripe(array $attributes = []): void
{
    tenant()->update(['stripe_account_id' => 'acct_test123', 'stripe_charges_enabled' => true, 'stripe_details_submitted' => true, 'platform_fee_percent' => 5, ...$attributes]);
}

/**
 * A signed webhook, as Stripe sends it
 *
 * @param  array<string, mixed>  $object
 */
function stripeWebhook(string $type, array $object, string $account = 'acct_test123'): TestResponse
{
    $payload = (string) json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => $type, 'account' => $account, 'data' => ['object' => $object]]);
    $time = time();
    $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_test');

    return test()->call('POST', route('central.webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload);
}

describe('connecting Stripe', function () {
    test('the page shows how far the account has got', function () {
        $this->get(route('admin.site-settings.payments.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('platformReady', true)->where('connected', false));

        connectStripe(['platform_fee_fixed' => 20]);

        $this->get(route('admin.site-settings.payments.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('chargesEnabled', true)->where('fee', ['percent' => 5, 'fixed' => '£0.20']));
    });

    test('connecting makes a Standard account once, and sends the business to Stripe', function () {
        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createAccount')->once()->andReturn('acct_new');
            $mock->shouldReceive('onboardingUrl')->twice()->andReturn('https://connect.stripe.com/setup/s/abc');
        });

        $this->post(route('admin.site-settings.payments.connect'))->assertRedirect('https://connect.stripe.com/setup/s/abc');
        $this->post(route('admin.site-settings.payments.connect'))->assertRedirect('https://connect.stripe.com/setup/s/abc');

        expect(tenant()->fresh()->stripe_account_id)->toBe('acct_new');
    });

    test('coming back from Stripe fetches the account\'s state, and it can be disconnected', function () {
        tenant()->update(['stripe_account_id' => 'acct_test123']);
        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('accountStatus')->with('acct_test123')->andReturn(['charges_enabled' => true, 'details_submitted' => true]));

        $this->get(route('admin.site-settings.payments.return'))->assertRedirect(route('admin.site-settings.payments.edit'));

        expect(tenant()->fresh()->canTakePayments())->toBeTrue();

        $this->delete(route('admin.site-settings.payments.disconnect'))->assertRedirect();

        expect(tenant()->fresh()->stripe_account_id)->toBeNull();
    });

    test('without the platform\'s keys, nothing can be connected', function () {
        config(['services.stripe.secret' => null]);

        $this->post(route('admin.site-settings.payments.connect'))->assertSessionHas('error');
    });

    test('it needs the manage settings permission and the Bookings module', function () {
        $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));
        $this->post(route('admin.site-settings.payments.connect'))->assertForbidden();

        tenant()->update(['modules' => [TenantModule::Crm]]);
        $this->get(route('admin.site-settings.payments.edit'))->assertNotFound();
    });
});

describe('requesting payment', function () {
    test('it makes a Stripe checkout on the business\'s account, with the platform fee, and sends the link', function () {
        connectStripe();
        $booking = Booking::factory()->create(['price' => 4000, 'customer_email' => 'morgan@example.test', 'service_name' => 'Haircut']);

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createCheckout')
                ->once()
                ->withArgs(fn (string $account, int $amount, string $currency, string $description, int $fee) => $account === 'acct_test123' && $amount === 1500 && $fee === 75 && str_contains($description, 'Haircut'))
                ->andReturn(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
        });

        $this->post(route('admin.bookings.bookings.payment-requests.store', $booking), ['amount' => '15.00'])->assertSessionHas('success');

        $payment = Payment::query()->sole();

        expect($payment->only(['amount', 'status', 'method', 'application_fee', 'stripe_checkout_session_id']))
            ->toBe(['amount' => 1500, 'status' => PaymentStatus::Pending, 'method' => PaymentMethod::Online, 'application_fee' => 75, 'stripe_checkout_session_id' => 'cs_test_1']);

        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => str_contains($mail->body, route('bookings.payments.show', $payment->public_id)));
    });

    test('it isn\'t possible until Stripe is connected, or for more than is owed or less than Stripe allows', function () {
        $booking = Booking::factory()->create(['price' => 4000]);

        $this->post(route('admin.bookings.bookings.payment-requests.store', $booking), ['amount' => '10'])->assertSessionHasErrors('amount');

        connectStripe();
        Payment::factory()->for($booking)->inPerson()->create(['amount' => 3000]);

        $this->post(route('admin.bookings.bookings.payment-requests.store', $booking), ['amount' => '20'])
            ->assertSessionHasErrors(['amount' => 'Only £10.00 is left to pay.']);
        $this->post(route('admin.bookings.bookings.payment-requests.store', $booking), ['amount' => '0.10'])
            ->assertSessionHasErrors(['amount' => 'The smallest amount is £0.30.']);
    });

    test('money taken in person is recorded as paid', function () {
        $booking = Booking::factory()->create(['price' => 4000]);

        $this->post(route('admin.bookings.bookings.payments.store', $booking), ['amount' => '40', 'method' => 'cash', 'note' => 'At the desk'])
            ->assertSessionHas('success');
        $this->post(route('admin.bookings.bookings.payments.store', $booking), ['amount' => '1', 'method' => 'online'])
            ->assertSessionHasErrors('method');

        expect(app(BookingPayments::class)->outstanding($booking))->toBe(0)
            ->and(Payment::query()->sole()->method)->toBe(PaymentMethod::Cash);
    });

    test('the booking suggests the deposit first, then what\'s left', function (string $type, int $amount, string $first) {
        $service = Service::factory()->create(['payment_requirement' => 'deposit', 'deposit_type' => $type, 'deposit_amount' => $amount]);
        $booking = Booking::factory()->for($service)->create(['price' => 5000]);

        $this->get(route('admin.bookings.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('payments.suggested', $first)->where('payments.outstanding', '£50.00'));

        Payment::factory()->for($booking)->inPerson()->create(['amount' => 1000]);

        $this->get(route('admin.bookings.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('payments.suggested', '40.00')->where('payments.paid', '£10.00'));
    })->with([
        'a percentage' => ['percent', 20, '10.00'],
        'a set amount' => ['fixed', 1500, '15.00'],
    ]);

    test('payments need their own permissions', function () {
        $booking = Booking::factory()->create(['price' => 4000]);
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

        $this->get(route('admin.bookings.payments.index'))->assertForbidden();
        $this->post(route('admin.bookings.bookings.payments.store', $booking), ['amount' => '1', 'method' => 'cash'])->assertForbidden();
        $this->get(route('admin.bookings.bookings.show', $booking))->assertInertia(fn (Assert $page) => $page->where('payments', null));
    });

    test('the payments list shows each with its fee', function () {
        Payment::factory()->create(['amount' => 2000, 'application_fee' => 100]);

        $this->get(route('admin.bookings.payments.index'))
            ->assertInertia(fn (Assert $page) => $page->where('payments.data.0.amount', '£20.00')->where('payments.data.0.fee', '£1.00')->where('columns.1.header', 'Customer'));
    });

    test('a deposit is saved on the service', function () {
        $service = Service::factory()->create();

        $this->put(route('admin.bookings.services.update', $service), [
            'name' => 'Haircut', 'booking_type' => 'appointment', 'price_type' => 'fixed', 'price' => '40', 'duration_minutes' => 30,
            'payment_requirement' => 'deposit', 'deposit_type' => 'fixed', 'deposit_amount' => '12.50',
        ])->assertSessionHasNoErrors();

        expect($service->fresh()->only(['deposit_amount']))->toBe(['deposit_amount' => 1250]);

        $this->put(route('admin.bookings.services.update', $service), [
            'name' => 'Haircut', 'booking_type' => 'appointment', 'price_type' => 'fixed', 'price' => '40', 'duration_minutes' => 30,
            'payment_requirement' => 'deposit', 'deposit_type' => 'percent', 'deposit_amount' => '150',
        ])->assertSessionHasErrors('deposit_amount');
    });
});

describe('Stripe\'s webhooks', function () {
    test('a completed checkout marks the payment paid, once, and late news doesn\'t undo it', function () {
        connectStripe();
        $payment = Payment::factory()->pending()->create(['stripe_checkout_session_id' => 'cs_test_9']);

        stripeWebhook('checkout.session.completed', ['id' => 'cs_test_9', 'payment_status' => 'paid', 'payment_intent' => 'pi_123'])->assertOk();
        stripeWebhook('checkout.session.expired', ['id' => 'cs_test_9'])->assertOk();

        expect($payment->fresh()->only(['status', 'stripe_payment_intent_id']))->toBe(['status' => PaymentStatus::Paid, 'stripe_payment_intent_id' => 'pi_123'])
            ->and($payment->fresh()->paid_at)->not->toBeNull();
    });

    test('an expired checkout is marked so', function () {
        connectStripe();
        $payment = Payment::factory()->pending()->create(['stripe_checkout_session_id' => 'cs_test_8']);

        stripeWebhook('checkout.session.expired', ['id' => 'cs_test_8'])->assertOk();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);
    });

    test('the business finishing its setup is noted', function () {
        connectStripe(['stripe_charges_enabled' => false]);

        stripeWebhook('account.updated', ['id' => 'acct_test123', 'charges_enabled' => true, 'details_submitted' => true])->assertOk();

        expect(tenant()->fresh()->canTakePayments())->toBeTrue();
    });

    test('an unsigned or unknown event changes nothing', function () {
        connectStripe();
        $payment = Payment::factory()->pending()->create(['stripe_checkout_session_id' => 'cs_test_7']);

        $this->postJson(route('central.webhooks.stripe'), ['type' => 'checkout.session.completed'])->assertStatus(400);
        stripeWebhook('checkout.session.completed', ['id' => 'cs_test_7', 'payment_status' => 'paid'], account: 'acct_someone_else')->assertOk();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    });
});

describe('the customer\'s link', function () {
    test('it sends them to pay while it can, and says how it went afterwards', function () {
        $pending = Payment::factory()->pending()->create();

        $this->get(route('bookings.payments.show', $pending->public_id))->assertRedirect($pending->checkout_url);
        $this->get(route('bookings.payments.show', ['publicId' => $pending->public_id, 'back' => 1]))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/Payment')->where('status', 'pending')->where('payUrl', $pending->checkout_url));

        $paid = Payment::factory()->create();

        $this->get(route('bookings.payments.show', $paid->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'paid')->where('payUrl', null));

        $this->get(route('bookings.payments.show', 'not-a-real-id'))->assertNotFound();
    });

    test('a link that has run out says so', function () {
        $expired = Payment::factory()->pending()->create(['expires_at' => now()->subMinute()]);

        $this->get(route('bookings.payments.show', $expired->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('expired', true)->where('payUrl', null));
    });
});

describe('refunds', function () {
    test('an online payment is refunded through Stripe, with the platform\'s share of its fee, and counts off what\'s paid', function () {
        connectStripe();
        $booking = Booking::factory()->create(['price' => 4000]);
        $payment = Payment::factory()->for($booking)->create(['amount' => 4000, 'application_fee' => 200, 'stripe_payment_intent_id' => 'pi_paid']);

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('refund')->once()->with('acct_test123', 'pi_paid', 1000, Mockery::any())->andReturn(['id' => 're_1', 'status' => 'succeeded']);
        });

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '10', 'reason' => 'Ran short'])->assertSessionHas('success');

        expect(Refund::query()->sole()->only(['amount', 'status', 'stripe_refund_id', 'application_fee_refunded', 'reason']))
            ->toBe(['amount' => 1000, 'status' => RefundStatus::Succeeded, 'stripe_refund_id' => 're_1', 'application_fee_refunded' => 50, 'reason' => 'Ran short'])
            ->and(app(BookingPayments::class)->outstanding($booking))->toBe(1000);

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '30.01'])
            ->assertSessionHasErrors(['amount' => 'Only £30.00 can be refunded.']);
    });

    test('money taken in person is noted as given back, without Stripe', function () {
        $payment = Payment::factory()->inPerson()->create(['amount' => 2000]);
        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldNotReceive('refund'));

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '20'])->assertSessionHas('success');
        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '1'])
            ->assertSessionHasErrors(['amount' => 'There\'s nothing left of this payment to refund.']);

        expect(Refund::query()->sole()->status)->toBe(RefundStatus::Succeeded);
    });

    test('Stripe turning a refund down says why, and a waiting link can\'t be refunded', function () {
        connectStripe();
        $payment = Payment::factory()->create(['amount' => 2000]);
        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('refund')->andThrow(InvalidRequestException::factory('Charge has been disputed.'));
        });

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '5'])
            ->assertSessionHasErrors(['amount' => 'Stripe couldn\'t refund it: Charge has been disputed.']);
        $this->post(route('admin.bookings.payments.refunds.store', Payment::factory()->pending()->create()), ['amount' => '5'])
            ->assertSessionHasErrors('amount');

        expect(Refund::query()->count())->toBe(0);
    });

    test('a refund the bank turns down later is marked failed, so it can be tried again', function () {
        connectStripe();
        $payment = Payment::factory()->create(['amount' => 2000]);
        $refund = Refund::factory()->for($payment)->pending()->create(['amount' => 2000, 'stripe_refund_id' => 're_late']);

        expect(app(BookingPayments::class)->refundable($payment))->toBe(0);

        stripeWebhook('refund.failed', ['id' => 're_late', 'object' => 'refund', 'status' => 'failed'])->assertOk();

        expect($refund->fresh()->status)->toBe(RefundStatus::Failed)
            ->and(app(BookingPayments::class)->refundable($payment))->toBe(2000);
    });
});

describe('cards for fees', function () {
    test('asking for a card makes a setup page on the business\'s account and sends the link', function () {
        connectStripe();
        $booking = Booking::factory()->create(['customer_name' => 'Morgan Reed', 'customer_email' => 'morgan@example.test']);

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createCustomer')->once()->with('acct_test123', 'Morgan Reed', 'morgan@example.test')->andReturn('cus_1');
            $mock->shouldReceive('createCardSetup')->once()->withArgs(fn (string $account, string $customer) => $account === 'acct_test123' && $customer === 'cus_1')
                ->andReturn(['id' => 'cs_setup_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_setup_1']);
        });

        $this->post(route('admin.bookings.bookings.card-requests.store', $booking))->assertSessionHas('success');

        $card = SavedCard::query()->sole();

        expect($card->only(['status', 'stripe_customer_id', 'stripe_checkout_session_id']))
            ->toBe(['status' => SavedCardStatus::Requested, 'stripe_customer_id' => 'cus_1', 'stripe_checkout_session_id' => 'cs_setup_1']);

        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => str_contains($mail->body, route('bookings.cards.show', $card->public_id)));

        $this->get(route('admin.bookings.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('payments.card', null)->where('payments.cardLink', route('bookings.cards.show', $card->public_id)));
    });

    test('a completed setup page saves the card, and an expired one is marked so', function () {
        connectStripe();
        $card = SavedCard::factory()->requested()->create(['stripe_checkout_session_id' => 'cs_setup_2']);
        $other = SavedCard::factory()->requested()->create(['stripe_checkout_session_id' => 'cs_setup_3']);

        $this->partialMock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('savedCard')->once()->with('acct_test123', 'seti_1')
            ->andReturn(['customer' => 'cus_2', 'payment_method' => 'pm_2', 'brand' => 'mastercard', 'last4' => '4444']));

        stripeWebhook('checkout.session.completed', ['id' => 'cs_setup_2', 'mode' => 'setup', 'setup_intent' => 'seti_1'])->assertOk();
        stripeWebhook('checkout.session.completed', ['id' => 'cs_setup_2', 'mode' => 'setup', 'setup_intent' => 'seti_1'])->assertOk();
        stripeWebhook('checkout.session.expired', ['id' => 'cs_setup_3', 'mode' => 'setup'])->assertOk();

        expect($card->fresh()->only(['status', 'stripe_payment_method_id', 'last4']))->toBe(['status' => SavedCardStatus::Saved, 'stripe_payment_method_id' => 'pm_2', 'last4' => '4444'])
            ->and($card->fresh()->label())->toBe('Mastercard ending 4444')
            ->and($other->fresh()->status)->toBe(SavedCardStatus::Expired);
    });

    test('paying online for a service with fees keeps the card too', function () {
        connectStripe();
        $booking = Booking::factory()->for(Service::factory()->withFees())->create(['price' => 4000]);

        $this->partialMock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createCheckout')->once()->withArgs(fn (...$arguments) => $arguments[9] === true)
                ->andReturn(['id' => 'cs_pay_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_pay_1']);
            $mock->shouldReceive('savedCard')->once()->with('acct_test123', null, 'pi_kept')
                ->andReturn(['customer' => 'cus_3', 'payment_method' => 'pm_3', 'brand' => 'visa', 'last4' => '4242']);
        });

        $this->post(route('admin.bookings.bookings.payment-requests.store', $booking), ['amount' => '40']);
        stripeWebhook('checkout.session.completed', ['id' => 'cs_pay_1', 'mode' => 'payment', 'payment_status' => 'paid', 'payment_intent' => 'pi_kept'])->assertOk();

        expect(SavedCard::query()->sole()->only(['booking_id', 'status', 'stripe_payment_method_id']))
            ->toBe(['booking_id' => $booking->id, 'status' => SavedCardStatus::Saved, 'stripe_payment_method_id' => 'pm_3']);
    });

    test('the customer\'s link sends them to Stripe while it\'s open, and says how it went afterwards', function () {
        $open = SavedCard::factory()->requested()->create();

        $this->get(route('bookings.cards.show', $open->public_id))->assertRedirect($open->checkout_url);

        $this->get(route('bookings.cards.show', SavedCard::factory()->create()->public_id))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/SavedCard')->where('status', 'saved')->where('saveUrl', null));
        $this->get(route('bookings.cards.show', SavedCard::factory()->requested()->create(['expires_at' => now()->subMinute()])->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('expired', true)->where('saveUrl', null));
    });
});

describe('no-show and late cancellation fees', function () {
    test('a no-show fee is charged to the saved card, once, and doesn\'t count towards the price', function () {
        connectStripe();
        $booking = Booking::factory()->for(Service::factory()->withFees())->status(BookingStatus::NoShow)->create(['price' => 4000]);
        SavedCard::factory()->for($booking)->create(['stripe_customer_id' => 'cus_4', 'stripe_payment_method_id' => 'pm_4']);

        $this->get(route('admin.bookings.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('payments.fee', ['purpose' => 'no_show_fee', 'label' => 'No-show fee', 'suggested' => '20.00'])->where('payments.card.label', 'Visa ending 4242'));

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('chargeSavedCard')->once()
                ->withArgs(fn (string $account, string $customer, string $method, int $amount, string $currency, int $fee) => $account === 'acct_test123' && $customer === 'cus_4' && $method === 'pm_4' && $amount === 2000 && $fee === 100)
                ->andReturn(['id' => 'pi_fee', 'status' => 'succeeded', 'error' => null]);
        });

        $this->post(route('admin.bookings.bookings.fees.store', $booking), ['amount' => '20'])->assertSessionHas('success');
        $this->post(route('admin.bookings.bookings.fees.store', $booking), ['amount' => '20'])->assertSessionHasErrors('amount');

        expect(Payment::query()->sole()->only(['purpose', 'status', 'amount', 'stripe_payment_intent_id']))
            ->toBe(['purpose' => PaymentPurpose::NoShowFee, 'status' => PaymentStatus::Paid, 'amount' => 2000, 'stripe_payment_intent_id' => 'pi_fee'])
            ->and(app(BookingPayments::class)->paid($booking))->toBe(0);
    });

    test('a declined card is noted, says why, and can be tried again', function () {
        connectStripe();
        $booking = Booking::factory()->cancelledLate()->create(['price' => 4000]);
        SavedCard::factory()->for($booking)->create();

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('chargeSavedCard')->twice()->andReturn(
                ['id' => 'pi_declined', 'status' => 'failed', 'error' => 'Your card has insufficient funds.'],
                ['id' => 'pi_ok', 'status' => 'succeeded', 'error' => null],
            );
        });

        $this->post(route('admin.bookings.bookings.fees.store', $booking), ['amount' => '10'])
            ->assertSessionHasErrors(['amount' => 'The card was declined: Your card has insufficient funds.']);
        $this->post(route('admin.bookings.bookings.fees.store', $booking), ['amount' => '10'])->assertSessionHas('success');

        expect(Payment::query()->orderBy('id')->pluck('status')->all())->toBe([PaymentStatus::Failed, PaymentStatus::Paid])
            ->and(Payment::query()->orderBy('id')->first()->failure_message)->toBe('Your card has insufficient funds.')
            ->and(Payment::query()->orderBy('id')->first()->purpose)->toBe(PaymentPurpose::LateCancellationFee);
    });

    test('no fee is due without a no-show or late cancellation, or without a saved card', function () {
        connectStripe();
        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldNotReceive('chargeSavedCard');
        });

        $confirmed = Booking::factory()->create(['price' => 4000]);
        SavedCard::factory()->for($confirmed)->create();
        $cancelledEarly = Booking::factory()->status(BookingStatus::Cancelled)->create(['price' => 4000]);
        SavedCard::factory()->for($cancelledEarly)->create();
        $noCard = Booking::factory()->status(BookingStatus::NoShow)->create(['price' => 4000]);

        $this->post(route('admin.bookings.bookings.fees.store', $confirmed), ['amount' => '10'])->assertSessionHasErrors('amount');
        $this->post(route('admin.bookings.bookings.fees.store', $cancelledEarly), ['amount' => '10'])->assertSessionHasErrors('amount');
        $this->post(route('admin.bookings.bookings.fees.store', $noCard), ['amount' => '10'])
            ->assertSessionHasErrors(['amount' => 'There\'s no card saved to charge.']);

        expect(Payment::query()->count())->toBe(0);
    });

    test('the fees are saved on the service', function () {
        $service = Service::factory()->create();

        $this->put(route('admin.bookings.services.update', $service), [
            'name' => 'Haircut', 'booking_type' => 'appointment', 'price_type' => 'fixed', 'price' => '40', 'duration_minutes' => 30,
            'no_show_fee_type' => 'percent', 'no_show_fee_amount' => '50', 'late_cancellation_fee_type' => 'fixed', 'late_cancellation_fee_amount' => '12.50',
        ])->assertSessionHasNoErrors();

        expect($service->fresh()->only(['no_show_fee_amount', 'late_cancellation_fee_amount']))->toBe(['no_show_fee_amount' => 50, 'late_cancellation_fee_amount' => 1250]);

        $this->put(route('admin.bookings.services.update', $service), [
            'name' => 'Haircut', 'booking_type' => 'appointment', 'price_type' => 'fixed', 'price' => '40', 'duration_minutes' => 30,
            'no_show_fee_type' => 'fixed', 'no_show_fee_amount' => '',
        ])->assertSessionHasNoErrors();

        expect($service->fresh()->only(['no_show_fee_type', 'no_show_fee_amount']))->toBe(['no_show_fee_type' => null, 'no_show_fee_amount' => null]);
    });

    test('refunds, cards and fees need the manage payments permission', function () {
        $booking = Booking::factory()->status(BookingStatus::NoShow)->create();
        $payment = Payment::factory()->for($booking)->inPerson()->create();
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings, BookingsPermission::ViewPayments]));

        $this->post(route('admin.bookings.payments.refunds.store', $payment), ['amount' => '1'])->assertForbidden();
        $this->post(route('admin.bookings.bookings.card-requests.store', $booking))->assertForbidden();
        $this->post(route('admin.bookings.bookings.fees.store', $booking), ['amount' => '10'])->assertForbidden();
    });
});
