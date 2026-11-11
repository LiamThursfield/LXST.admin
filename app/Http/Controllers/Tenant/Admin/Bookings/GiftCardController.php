<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Enums\Bookings\ServicePriceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\GiftCardIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\GiftCardStoreRequest;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingPayments;
use App\Services\Bookings\GiftCards;
use App\Services\Payments\StripeGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Gift cards and vouchers: the list, issuing or selling one, and seeing where it's been used
 */
class GiftCardController extends Controller
{
    public function index(GiftCardIndexRequest $request, GiftCards $giftCards, BookingPayments $payments): Response
    {
        $list = QueryBuilder::for(GiftCard::query()->with('service'), $request)
            ->allowedFilters(
                AllowedFilter::exact('kind'),
                AllowedFilter::callback('status', fn (Builder $query, string $value) => $query->whereCurrentStatus(GiftCardStatus::from($value))),
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                    ->where('code', 'like', '%'.GiftCards::formatCode($value).'%')
                    ->orWhere('recipient_name', 'like', "%{$value}%")
                    ->orWhere('purchaser_name', 'like', "%{$value}%")
                    ->orWhere('recipient_email', 'like', "%{$value}%")
                    ->orWhere('purchaser_email', 'like', "%{$value}%"))),
            )
            ->defaultSort('-id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return Inertia::render('admin/bookings/gift-card/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');

                return $validated == null ? null : collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'code', 'header' => __('bookings.gift_cards.columns.code')],
                ['accessorKey' => 'value', 'header' => __('bookings.gift_cards.columns.value')],
                ['accessorKey' => 'balance', 'header' => __('bookings.gift_cards.columns.balance')],
                ['accessorKey' => 'recipient', 'header' => __('bookings.gift_cards.columns.recipient')],
                ['accessorKey' => 'expires_on', 'header' => __('bookings.gift_cards.columns.expires_on')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.gift_cards.columns.status')],
            ],
            'giftCards' => [
                'data' => $list->getCollection()->map(fn (GiftCard $card) => $this->row($card, $giftCards, $payments))->values(),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'last_page' => $list->lastPage(),
                    'total' => $list->total(),
                    'per_page' => $list->perPage(),
                ],
            ],
            'statuses' => GiftCardStatus::asSelectItems(),
            'kinds' => GiftCardKind::asSelectItems(),
            'canCreate' => $request->user()?->can(BookingsPermission::ManagePayments) ?? false,
        ]);
    }

    public function create(GiftCards $giftCards, StripeGateway $stripe): Response
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $tenant = tenant();
        $methods = array_map(fn (string $method) => ['value' => $method, 'label' => __("bookings.gift_cards.methods.{$method}")], GiftCardStoreRequest::METHODS);

        // Selling online needs the tenant's Stripe account
        if (! ($tenant instanceof Tenant && $tenant->canTakePayments() && $stripe->isConfigured())) {
            $methods = array_values(array_filter($methods, fn (array $method) => $method['value'] !== 'online'));
        }

        return Inertia::render('admin/bookings/gift-card/Create', [
            'kinds' => GiftCardKind::asSelectItems(),
            'services' => $this->servicesWithPrices(),
            'methods' => $methods,
            'defaultExpiry' => $giftCards->defaultExpiry()?->toDateString(),
        ]);
    }

    public function store(GiftCardStoreRequest $request, GiftCards $giftCards): RedirectResponse
    {
        $card = $giftCards->issue($request->details(), $request->user());

        return redirect()->route('admin.bookings.gift-cards.show', $card)->with('success', __(
            $card->status === GiftCardStatus::Pending ? 'bookings.gift_cards.flash.link_sent' : 'bookings.gift_cards.flash.issued',
        ));
    }

    public function show(Request $request, GiftCard $giftCard, GiftCards $giftCards, BookingPayments $payments): Response
    {
        Gate::authorize(BookingsPermission::ViewPayments);

        $giftCard->load(['service', 'payments.booking', 'payments.refunds']);

        return Inertia::render('admin/bookings/gift-card/Show', [
            'giftCard' => [
                ...$this->row($giftCard, $giftCards, $payments),
                'kind' => $giftCard->kind->value,
                'kind_label' => $giftCard->kind->label(),
                'price_paid' => $giftCard->payment_method === null ? null : $payments->format($giftCard->price_paid, $giftCard->currency),
                'method_label' => $giftCard->payment_method?->label() ?? __('bookings.gift_cards.methods.free'),
                'fee' => $giftCard->application_fee > 0 ? $payments->format($giftCard->application_fee, $giftCard->currency) : null,
                'purchaser_name' => $giftCard->purchaser_name,
                'purchaser_email' => $giftCard->purchaser_email,
                'recipient_name' => $giftCard->recipient_name,
                'recipient_email' => $giftCard->recipient_email,
                'message' => $giftCard->message,
                'issued_at' => $giftCard->created_at?->format('D j M Y, H:i'),
                'sent_at' => $giftCard->sent_at?->format('D j M Y, H:i'),
                'link' => route('bookings.gift-cards.show', $giftCard->public_id),
                'canSend' => in_array($giftCard->currentStatus(), [GiftCardStatus::Active, GiftCardStatus::Used], true)
                    && filled($giftCard->recipient_email ?? $giftCard->purchaser_email),
                'canCancel' => in_array($giftCard->status, [GiftCardStatus::Active, GiftCardStatus::Pending], true),
                'uses' => $giftCard->payments->sortByDesc('id')->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'when' => $payment->paid_at?->setTimezone($payment->booking->timezone)->format('D j M Y, H:i'),
                    'customer_name' => $payment->booking->customer_name,
                    'service_name' => $payment->booking->service_name,
                    'amount' => $payments->format($payment->amount, $payment->currency),
                    'refunded' => ($refunded = $payments->refunded($payment)) > 0 ? $payments->format($refunded, $payment->currency) : null,
                    'booking_url' => route('admin.bookings.bookings.show', $payment->booking_id),
                ])->values(),
            ],
            'canManage' => $request->user()?->can(BookingsPermission::ManagePayments) ?? false,
        ]);
    }

    /**
     * Emails the card again to whoever it's for
     */
    public function send(GiftCard $giftCard, GiftCards $giftCards): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        return $giftCards->send($giftCard)
            ? redirect()->back()->with('success', __('bookings.gift_cards.flash.sent'))
            : redirect()->back()->with('error', __('bookings.gift_cards.flash.not_sent'));
    }

    /**
     * Stops it being used. Any money paid for it is given back in Stripe or in person, not here.
     */
    public function cancel(GiftCard $giftCard, GiftCards $giftCards): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $giftCards->cancel($giftCard);

        return redirect()->back()->with('success', __('bookings.gift_cards.flash.cancelled'));
    }

    /**
     * Services with a set price, which a voucher can be for
     *
     * @return list<array{value: int, label: string}>
     */
    public static function servicesWithPrices(bool $onlineOnly = false): array
    {
        $payments = app(BookingPayments::class);

        return array_values(Service::query()
            ->where('price_type', ServicePriceType::Fixed)
            ->where('price', '>', 0)
            ->when($onlineOnly, fn (Builder $query) => $query->where('is_bookable_online', true))
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => ['value' => $service->id, 'label' => "{$service->name} ({$payments->format((int) $service->price)})"])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(GiftCard $card, GiftCards $giftCards, BookingPayments $payments): array
    {
        $status = $card->currentStatus();

        return [
            'id' => $card->id,
            'code' => $card->code,
            'value' => $giftCards->valueLabel($card),
            'balance' => $status === GiftCardStatus::Pending ? null : $giftCards->balanceLabel($card),
            'recipient' => $card->recipient_name ?? $card->purchaser_name,
            'expires_on' => $card->expires_on?->format('j M Y') ?? __('bookings.gift_cards.never'),
            'status' => $status->value,
            'status_label' => $status->label(),
            'show_url' => route('admin.bookings.gift-cards.show', $card),
        ];
    }
}
