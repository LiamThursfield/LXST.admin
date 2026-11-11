<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\RefundStatus;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Refund;
use Carbon\CarbonImmutable;

/**
 * Money in and out by month, from the platform's own records: taken towards bookings online and in
 * person, taken from gift cards, fees, gift cards sold, refunds, and the platform's fees
 */
class PaymentReports
{
    /** The columns of a month's totals, in minor units */
    public const array COLUMNS = ['online', 'in_person', 'gift_card', 'fees', 'gift_card_sales', 'refunds', 'platform_fees', 'net'];

    /**
     * The last few months' totals, newest first, and what they add up to. Net is the new money:
     * payments from gift cards don't count, as the money came in when the card was sold.
     *
     * @return array{months: list<array<string, int|string>>, totals: array<string, int>}
     */
    public function monthly(int $months = 12): array
    {
        $from = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $rows = [];

        for ($month = $from; $month->lte(now()); $month = $month->addMonth()) {
            $rows[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'label' => $month->format('F Y'), ...array_fill_keys(self::COLUMNS, 0)];
        }

        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->get(['amount', 'method', 'purpose', 'application_fee', 'paid_at']);

        foreach ($payments as $payment) {
            $key = $payment->paid_at?->format('Y-m');

            if ($key === null || ! isset($rows[$key])) {
                continue;
            }

            $column = match (true) {
                $payment->purpose !== PaymentPurpose::Booking => 'fees',
                $payment->method === PaymentMethod::Online => 'online',
                $payment->method === PaymentMethod::GiftCard => 'gift_card',
                default => 'in_person',
            };

            $rows[$key][$column] += $payment->amount;
            $rows[$key]['platform_fees'] += $payment->application_fee;
        }

        $refunds = Refund::query()
            ->where('status', '!=', RefundStatus::Failed)
            ->where('created_at', '>=', $from)
            ->with('payment:id,method')
            ->get(['payment_id', 'amount', 'application_fee_refunded', 'created_at']);

        foreach ($refunds as $refund) {
            $key = $refund->created_at?->format('Y-m');

            // Money put back on a gift card never left the business
            if ($key === null || ! isset($rows[$key]) || $refund->payment->method === PaymentMethod::GiftCard) {
                continue;
            }

            $rows[$key]['refunds'] += $refund->amount;
            $rows[$key]['platform_fees'] -= $refund->application_fee_refunded;
        }

        $sales = GiftCard::query()
            ->whereNotNull('payment_method')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->get(['price_paid', 'application_fee', 'paid_at']);

        foreach ($sales as $card) {
            $key = $card->paid_at?->format('Y-m');

            if ($key === null || ! isset($rows[$key])) {
                continue;
            }

            $rows[$key]['gift_card_sales'] += $card->price_paid;
            $rows[$key]['platform_fees'] += $card->application_fee;
        }

        foreach ($rows as $key => $row) {
            $rows[$key]['net'] = (int) $row['online'] + (int) $row['in_person'] + (int) $row['fees'] + (int) $row['gift_card_sales'] - (int) $row['refunds'];
        }

        $totals = array_fill_keys(self::COLUMNS, 0);

        foreach ($rows as $row) {
            foreach (self::COLUMNS as $column) {
                $totals[$column] += (int) $row[$column];
            }
        }

        return ['months' => array_values(array_reverse($rows)), 'totals' => $totals];
    }
}
