<?php

use App\Enums\Bookings\InvoiceStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\QuoteStatus;
use App\Enums\TenantModule;
use App\Mail\Bookings\BookingMessageMail;
use App\Models\Bookings\Booking;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Invoice;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Quote;
use App\Models\Bookings\Refund;
use App\Models\Settings\BookingsSettings;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

beforeEach(function () {
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_fake']);
    $this->actingAs($this->createUserWithPermissions([...BookingsPermission::cases(), ...SettingsPermission::cases()]));
});

/**
 * Lines as the form sends them, with prices in pounds
 *
 * @return array<string, mixed>
 */
function quoteLines(array $extra = []): array
{
    return [
        'lines' => [
            ['description' => 'Labour', 'quantity' => '2.5', 'unit_price' => '40'],
            ['description' => 'Materials', 'quantity' => '1', 'unit_price' => '25.50'],
        ],
        'notes' => 'Includes everything discussed.',
        ...$extra,
    ];
}

describe('quotes', function () {
    test('a quote adds up its lines with the business\'s tax, and is numbered in order', function () {
        app(BookingsSettings::class)->fill(['tax_rate' => 20.0])->save();
        $booking = Booking::factory()->create(['price' => null]);

        $this->post(route('admin.bookings.bookings.quotes.store', $booking), quoteLines(['valid_until' => today()->addDays(30)->toDateString()]))
            ->assertRedirect()->assertSessionHas('success');
        $this->post(route('admin.bookings.bookings.quotes.store', $booking), quoteLines());

        [$first, $second] = Quote::query()->orderBy('id')->get();

        expect($first->only(['number', 'status', 'subtotal', 'tax_rate', 'tax', 'total']))
            ->toBe(['number' => 'QUO-0001', 'status' => QuoteStatus::Draft, 'subtotal' => 12550, 'tax_rate' => '20.00', 'tax' => 2510, 'total' => 15060])
            ->and($first->lines->pluck('total')->all())->toBe([10000, 2550])
            ->and($second->number)->toBe('QUO-0002');

        Mail::assertNothingSent();
    });

    test('saving and sending sends the customer the link, and it can be changed until they answer', function () {
        $booking = Booking::factory()->create(['customer_email' => 'sasha@example.test']);

        $this->post(route('admin.bookings.bookings.quotes.store', $booking), quoteLines(['send' => true]))->assertSessionHas('success');

        $quote = Quote::query()->sole();

        expect($quote->status)->toBe(QuoteStatus::Sent);
        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => str_contains($mail->body, route('bookings.quotes.show', $quote->public_id)));

        $this->put(route('admin.bookings.quotes.update', $quote), ['lines' => [['description' => 'All of it', 'quantity' => '1', 'unit_price' => '99']]])
            ->assertSessionHas('success');

        expect($quote->fresh()->total)->toBe(9900)
            ->and($quote->lines()->count())->toBe(1);

        $quote->update(['status' => QuoteStatus::Accepted]);

        $this->put(route('admin.bookings.quotes.update', $quote), quoteLines())->assertSessionHasErrors('lines');
    });

    test('the customer accepting sets the booking\'s price; declining doesn\'t', function () {
        $booking = Booking::factory()->create(['price' => null]);
        $quote = Quote::factory()->for($booking)->withLines()->create(['subtotal' => 30000, 'total' => 30000]);

        $this->get(route('bookings.quotes.show', $quote->public_id))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/Quote')->where('open', true)->where('document.total', '£300.00'));

        $this->post(route('bookings.quotes.accept', $quote->public_id))->assertRedirect(route('bookings.quotes.show', $quote->public_id));

        expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
            ->and($booking->fresh()->price)->toBe(30000);

        // Answered, so it can't be answered again
        $this->post(route('bookings.quotes.decline', $quote->public_id))->assertSessionHasErrors('quote');

        $other = Quote::factory()->create();
        $this->post(route('bookings.quotes.decline', $other->public_id));

        expect($other->fresh()->status)->toBe(QuoteStatus::Declined)
            ->and($other->booking->fresh()->price)->not->toBe($other->total);
    });

    test('customers can\'t see drafts, or answer expired quotes; staff can mark either', function () {
        $draft = Quote::factory()->draft()->create();
        $expired = Quote::factory()->expired()->create();

        $this->get(route('bookings.quotes.show', $draft->public_id))->assertNotFound();

        $this->get(route('bookings.quotes.show', $expired->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'expired')->where('open', false));
        $this->post(route('bookings.quotes.accept', $expired->public_id))->assertSessionHasErrors('quote');

        $this->post(route('admin.bookings.quotes.accept', $expired))->assertSessionHas('success');
        expect($expired->fresh()->status)->toBe(QuoteStatus::Accepted);

        $this->post(route('admin.bookings.quotes.decline', $draft))->assertSessionHas('success');
        expect($draft->fresh()->status)->toBe(QuoteStatus::Declined);
    });

    test('the list counts sent quotes past their date as expired', function () {
        Quote::factory()->create(['number' => 'QUO-0001']);
        Quote::factory()->expired()->create(['number' => 'QUO-0002']);

        $this->get(route('admin.bookings.quotes.index', ['filter' => ['status' => 'expired']]))
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/quote/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.number', 'QUO-0002'));

        $this->get(route('admin.bookings.quotes.index', ['filter' => ['status' => 'sent']]))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.number', 'QUO-0001'));
    });
});

describe('invoices', function () {
    test('a new invoice starts with the accepted quote\'s lines', function () {
        $booking = Booking::factory()->create();
        $quote = Quote::factory()->for($booking)->accepted()->withLines()->create(['subtotal' => 30000, 'total' => 30000]);

        $this->get(route('admin.bookings.bookings.invoices.create', $booking))
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/invoice/Form')
                ->where('quoteId', $quote->id)
                ->where('lines.0.unit_price', '300.00'));
    });

    test('without a quote, it starts with the booking\'s price, before any tax', function () {
        app(BookingsSettings::class)->fill(['tax_rate' => 20.0])->save();
        $booking = Booking::factory()->create(['price' => 6000, 'service_name' => 'Haircut']);

        $this->get(route('admin.bookings.bookings.invoices.create', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('lines.0.description', 'Haircut')->where('lines.0.unit_price', '50.00'));
    });

    test('issuing one numbers it, sets the booking\'s price, and only one is open at a time', function () {
        $booking = Booking::factory()->create(['price' => 1000, 'customer_email' => 'sasha@example.test']);

        $this->post(route('admin.bookings.bookings.invoices.store', $booking), quoteLines(['send' => true, 'due_on' => today()->addDays(14)->toDateString()]))
            ->assertSessionHas('success');

        $invoice = Invoice::query()->sole();

        expect($invoice->only(['number', 'status', 'total']))->toBe(['number' => 'INV-0001', 'status' => InvoiceStatus::Sent, 'total' => 12550])
            ->and($booking->fresh()->price)->toBe(12550);
        Mail::assertSent(BookingMessageMail::class, fn (BookingMessageMail $mail) => str_contains($mail->body, route('bookings.invoices.show', $invoice->public_id)));

        $this->post(route('admin.bookings.bookings.invoices.store', $booking), quoteLines())->assertSessionHasErrors('lines');
        $this->get(route('admin.bookings.bookings.invoices.create', $booking))->assertRedirect(route('admin.bookings.invoices.show', $invoice));

        $this->post(route('admin.bookings.invoices.void', $invoice))->assertSessionHas('success');
        $this->post(route('admin.bookings.bookings.invoices.store', $booking), quoteLines())->assertSessionHas('success');

        expect(Invoice::query()->latest('id')->first()->number)->toBe('INV-0002');
    });

    test('lines that add up to nothing aren\'t invoiced', function () {
        $booking = Booking::factory()->create();

        $this->post(route('admin.bookings.bookings.invoices.store', $booking), ['lines' => [
            ['description' => 'Work', 'quantity' => '1', 'unit_price' => '20'],
            ['description' => 'Discount', 'quantity' => '1', 'unit_price' => '-20'],
        ]])->assertSessionHasErrors(['lines' => 'The lines must add up to more than nothing.']);
    });

    test('what\'s owed counts every payment towards the booking, and shows paid and overdue', function () {
        $booking = Booking::factory()->create(['price' => 10000]);
        $invoice = Invoice::factory()->for($booking)->overdue()->withLines()->create();

        $this->get(route('bookings.invoices.show', $invoice->public_id))
            ->assertInertia(fn (Assert $page) => $page->component('public/bookings/Invoice')->where('status', 'overdue')->where('document.owed', '£100.00'));

        Payment::factory()->for($booking)->inPerson()->create(['amount' => 4000]);
        $card = GiftCard::factory()->create();
        Payment::factory()->for($booking)->fromGiftCard($card)->create(['amount' => 6000]);

        $this->get(route('bookings.invoices.show', $invoice->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'paid')->where('document.owed', '£0.00')->where('document.paid', '£100.00')->where('canPay', false));

        // A refund means some is owed again
        Refund::factory()->for(Payment::query()->where('method', PaymentMethod::Cash)->sole())->inPerson()->create(['amount' => 1500]);

        $this->get(route('admin.bookings.invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'overdue')->where('document.owed', '£15.00'));
    });

    test('paying sends the customer to Stripe for what\'s owed, reusing an open link', function () {
        tenant()->update(['stripe_account_id' => 'acct_test123', 'stripe_charges_enabled' => true]);
        $booking = Booking::factory()->create(['price' => 10000]);
        $invoice = Invoice::factory()->for($booking)->create();
        Payment::factory()->for($booking)->inPerson()->create(['amount' => 2500]);

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createCheckout')->once()
                ->withArgs(fn (string $account, int $amount) => $amount === 7500)
                ->andReturn(['id' => 'cs_test_invoice', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_invoice']);
        });

        $this->get(route('bookings.invoices.show', $invoice->public_id))->assertInertia(fn (Assert $page) => $page->where('canPay', true));

        $this->post(route('bookings.invoices.pay', $invoice->public_id))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_invoice');
        $this->post(route('bookings.invoices.pay', $invoice->public_id))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_invoice');

        // No message, as they're already paying
        Mail::assertNothingSent();
        expect(Payment::query()->where('status', 'pending')->count())->toBe(1);
    });

    test('a void invoice owes nothing and can\'t be paid', function () {
        $invoice = Invoice::factory()->void()->create();

        $this->get(route('bookings.invoices.show', $invoice->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'void')->where('document.owed', '£0.00'));
        $this->post(route('bookings.invoices.pay', $invoice->public_id))->assertSessionHasErrors('invoice');
    });
});

describe('the payment report', function () {
    test('it totals each month by kind, with refunds and platform fees, and net new money', function () {
        $booking = Booking::factory()->create();
        Payment::factory()->for($booking)->create(['amount' => 10000, 'application_fee' => 500]);
        $cash = Payment::factory()->for($booking)->inPerson()->create(['amount' => 3000]);
        Payment::factory()->for($booking)->fromGiftCard(GiftCard::factory()->free()->create())->create(['amount' => 2000]);
        Payment::factory()->for($booking)->fee()->create(['amount' => 1500]);
        Payment::factory()->for($booking)->pending()->create(['amount' => 9999]);
        Refund::factory()->for($cash)->inPerson()->create(['amount' => 1000]);
        GiftCard::factory()->worth(5000)->create(['application_fee' => 250]);
        // Last year, so outside the report
        Payment::factory()->for($booking)->create(['amount' => 7777, 'paid_at' => now()->subYears(2)]);

        $this->get(route('admin.bookings.reports.payments', ['months' => 3]))
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/report/Payments')
                ->has('rows', 3)
                ->where('rows.0.online', '£100.00')
                ->where('rows.0.in_person', '£30.00')
                ->where('rows.0.gift_card', '£20.00')
                ->where('rows.0.fees', '£15.00')
                ->where('rows.0.gift_card_sales', '£50.00')
                ->where('rows.0.refunds', '£10.00')
                ->where('rows.0.platform_fees', '£7.50')
                ->where('rows.0.net', '£185.00')
                ->where('totals.net', '£185.00')
                ->missing('stripe'));
    });

    test('the Stripe balance and payouts load once the page has shown', function () {
        tenant()->update(['stripe_account_id' => 'acct_test123', 'stripe_charges_enabled' => true]);

        $this->partialMock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('balance')->with('acct_test123')->andReturn(['available' => ['GBP' => 12000], 'pending' => ['GBP' => 3000]]);
            $mock->shouldReceive('payouts')->with('acct_test123')->andReturn([
                ['id' => 'po_1', 'amount' => 45000, 'currency' => 'GBP', 'status' => 'paid', 'arrival_date' => now()->subDay()->timestamp],
            ]);
        });

        $this->get(route('admin.bookings.reports.payments'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stripe.available.0', '£120.00')
                ->where('stripe.pending.0', '£30.00')
                ->where('stripe.payouts.0.amount', '£450.00')
                ->where('stripe.payouts.0.status', 'Paid')));
    });
});

test('quotes, invoices and the report need the payment permissions and the bookings module', function () {
    $booking = Booking::factory()->create();
    $quote = Quote::factory()->for($booking)->create();
    $invoice = Invoice::factory()->for($booking)->create();

    $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

    $this->get(route('admin.bookings.quotes.index'))->assertForbidden();
    $this->get(route('admin.bookings.invoices.show', $invoice))->assertForbidden();
    $this->get(route('admin.bookings.reports.payments'))->assertForbidden();

    $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings, BookingsPermission::ViewPayments]));

    $this->get(route('admin.bookings.quotes.show', $quote))->assertOk();
    $this->post(route('admin.bookings.bookings.quotes.store', $booking), quoteLines())->assertForbidden();
    $this->post(route('admin.bookings.invoices.void', $invoice))->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Crm]]);

    $this->get(route('admin.bookings.invoices.index'))->assertNotFound();
    $this->get(route('bookings.quotes.show', $quote->public_id))->assertNotFound();
});

test('the settings save tax and terms for quotes and invoices', function () {
    $this->put(route('admin.site-settings.payments.update'), [
        'gift_card_months' => 12,
        'gift_cards_online' => false,
        'tax_rate' => '20',
        'tax_label' => 'VAT',
        'tax_number' => 'GB123456789',
        'business_details' => "1 High Street\nAnytown",
        'quote_valid_days' => 30,
        'invoice_due_days' => '',
    ])->assertSessionHas('success');

    $settings = app(BookingsSettings::class);
    $settings->refresh();

    expect($settings->tax_rate)->toBe(20.0)
        ->and($settings->tax_number)->toBe('GB123456789')
        ->and($settings->invoice_due_days)->toBeNull();
});
