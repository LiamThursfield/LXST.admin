<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\PaymentIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\PaymentRecordRequest;
use App\Http\Requests\Tenant\Admin\Bookings\PaymentRequestRequest;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Services\Bookings\BookingPayments;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Money towards bookings: asking for it online, recording what's taken in person, and the list of all of it
 */
class BookingPaymentController extends Controller
{
    public function index(PaymentIndexRequest $request, BookingPayments $payments): Response
    {
        $list = QueryBuilder::for(Payment::query()->with('booking'), $request)
            ->allowedFilters(AllowedFilter::exact('status'), AllowedFilter::exact('method'))
            ->defaultSort('-id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return Inertia::render('admin/bookings/payment/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');

                return $validated == null ? null : collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'when', 'header' => __('bookings.payments.columns.when')],
                ['accessorKey' => 'customer_name', 'header' => app(Terminology::class)->translate('bookings.payments.columns.customer')],
                ['accessorKey' => 'amount', 'header' => __('bookings.payments.columns.amount')],
                ['accessorKey' => 'fee', 'header' => __('bookings.payments.columns.fee')],
                ['accessorKey' => 'method_label', 'header' => __('bookings.payments.columns.method')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.payments.columns.status')],
            ],
            'payments' => [
                'data' => $list->getCollection()->map(fn (Payment $payment) => [
                    ...$this->row($payment, $payments),
                    'customer_name' => $payment->booking->customer_name,
                    'booking_url' => route('admin.bookings.bookings.show', $payment->booking_id),
                ])->values(),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'last_page' => $list->lastPage(),
                    'total' => $list->total(),
                    'per_page' => $list->perPage(),
                ],
            ],
            'statuses' => PaymentStatus::asSelectItems(),
        ]);
    }

    /**
     * Sends the customer a link to pay online
     */
    public function store(PaymentRequestRequest $request, Booking $booking, BookingPayments $payments): RedirectResponse
    {
        $payments->request($booking, $request->amountInMinorUnits(), $request->user());

        return redirect()->back()->with('success', __('bookings.payments.flash.requested'));
    }

    /**
     * Records money taken in person
     */
    public function record(PaymentRecordRequest $request, Booking $booking, BookingPayments $payments): RedirectResponse
    {
        $payments->record($booking, $request->amountInMinorUnits(), PaymentMethod::from($request->string('method')->toString()), $request->validated('note'), $request->user());

        return redirect()->back()->with('success', __('bookings.payments.flash.recorded'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Payment $payment, BookingPayments $payments): array
    {
        return [
            'id' => $payment->id,
            'when' => ($payment->paid_at ?? $payment->created_at)?->setTimezone($payment->booking->timezone)->format('D j M Y, H:i'),
            'amount' => $payments->format($payment->amount),
            'fee' => $payment->application_fee > 0 ? $payments->format($payment->application_fee) : null,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'note' => $payment->note,
            'link' => $payment->isPayable() ? route('bookings.payments.show', $payment->public_id) : null,
        ];
    }
}
