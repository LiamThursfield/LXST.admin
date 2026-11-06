<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\SavedCardStatus;
use App\Http\Controllers\Controller;
use App\Models\Bookings\SavedCard;
use App\Models\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The link sent to a customer to save a card: it takes them to Stripe while it can, and says how it
 * went afterwards
 */
class SavedCardController extends Controller
{
    public function show(string $publicId, GeneralSettings $general): Response|RedirectResponse|SymfonyResponse
    {
        $card = SavedCard::query()->with('booking')->where('public_id', $publicId)->firstOrFail();

        if ($card->isOpen() && ! request()->boolean('back')) {
            return Inertia::location((string) $card->checkout_url);
        }

        return Inertia::render('public/bookings/SavedCard', [
            'business' => $general->site_name,
            'status' => $card->status->value,
            'expired' => $card->status === SavedCardStatus::Expired || ($card->status === SavedCardStatus::Requested && ! $card->isOpen()),
            'service' => $card->booking->service_name,
            'saveUrl' => $card->isOpen() ? (string) $card->checkout_url : null,
        ]);
    }
}
