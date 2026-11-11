<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingPayments;
use App\Services\Bookings\PaymentReports;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Money in and out by month from the platform's own records, and the business's Stripe balance and
 * payouts, fetched from Stripe once the page has shown
 */
class PaymentReportController extends Controller
{
    /** How many months the report can cover */
    public const array PERIODS = [3, 6, 12, 24];

    public function __invoke(Request $request, PaymentReports $reports, BookingPayments $payments, StripeGateway $stripe): Response
    {
        Gate::authorize(BookingsPermission::ViewPayments);

        $months = in_array($request->integer('months'), self::PERIODS, true) ? $request->integer('months') : 12;
        $report = $reports->monthly($months);
        $format = fn (array $row) => collect(PaymentReports::COLUMNS)->mapWithKeys(fn (string $column) => [$column => $payments->format((int) $row[$column])])->all();

        return Inertia::render('admin/bookings/report/Payments', [
            'months' => $months,
            'periods' => array_map(fn (int $period) => ['value' => $period, 'label' => trans_choice('bookings.reports.period', $period, ['count' => $period])], self::PERIODS),
            'columns' => array_map(fn (string $column) => ['key' => $column, 'label' => __("bookings.reports.columns.{$column}")], PaymentReports::COLUMNS),
            'rows' => array_map(fn (array $row) => ['month' => $row['month'], 'label' => $row['label'], ...$format($row)], $report['months']),
            'totals' => $format($report['totals']),
            'stripe' => Inertia::defer(fn () => $this->stripe($stripe, $payments)),
        ]);
    }

    /**
     * The business's Stripe balance and latest payouts, or why they can't be shown
     *
     * @return array<string, mixed>
     */
    protected function stripe(StripeGateway $stripe, BookingPayments $payments): array
    {
        $tenant = tenant();
        $accountId = $tenant instanceof Tenant ? $tenant->getAttribute('stripe_account_id') : null;

        if (! is_string($accountId) || $accountId === '' || ! $stripe->isConfigured()) {
            return ['connected' => false, 'error' => null, 'available' => [], 'pending' => [], 'payouts' => []];
        }

        try {
            $balance = $stripe->balance($accountId);
            $payouts = $stripe->payouts($accountId);
        } catch (Throwable $exception) {
            report($exception);

            return ['connected' => true, 'error' => __('bookings.reports.stripe_failed'), 'available' => [], 'pending' => [], 'payouts' => []];
        }

        $amounts = fn (array $byCurrency) => array_map(
            fn (string $currency, int $amount) => $payments->format($amount, $currency),
            array_keys($byCurrency),
            $byCurrency,
        );

        return [
            'connected' => true,
            'error' => null,
            'available' => $amounts($balance['available']),
            'pending' => $amounts($balance['pending']),
            'payouts' => array_map(fn (array $payout) => [
                'id' => $payout['id'],
                'amount' => $payments->format($payout['amount'], $payout['currency']),
                'status' => __("bookings.reports.payout_statuses.{$payout['status']}"),
                'arrives' => Carbon::createFromTimestamp($payout['arrival_date'])->format('j M Y'),
            ], $payouts),
        ];
    }
}
