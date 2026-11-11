<x-mail::message>
{{ __('bookings.gift_cards.mail.payment_intro', ['value' => $value, 'business' => $business, 'price' => $price]) }}

<x-mail::button :url="$link">
{{ __('bookings.gift_cards.mail.pay', ['price' => $price]) }}
</x-mail::button>

{{ __('bookings.gift_cards.mail.payment_outro') }}
</x-mail::message>
