<x-mail::message>
{{-- Names and the message are the customer's own words: shown as plain text, never as HTML --}}
# {{ $recipientName ? __('bookings.gift_cards.mail.greeting', ['name' => $recipientName]) : __('bookings.gift_cards.mail.greeting_anonymous') }}

{{ $purchaserName ? __('bookings.gift_cards.mail.from', ['name' => $purchaserName, 'value' => $value, 'business' => $business]) : __('bookings.gift_cards.mail.intro', ['value' => $value, 'business' => $business]) }}

@if ($personalMessage)
<x-mail::panel>
{!! nl2br(e($personalMessage)) !!}
</x-mail::panel>
@endif

{{ __('bookings.gift_cards.mail.code') }}

# {{ $code }}

@if ($expiresOn)
{{ __('bookings.gift_cards.mail.expires', ['date' => $expiresOn]) }}
@endif

<x-mail::button :url="$link">
{{ __('bookings.gift_cards.mail.view') }}
</x-mail::button>
</x-mail::message>
