<x-mail::message>
{{-- The tenant's own wording: shown as plain text with its line breaks, never as HTML --}}
{!! nl2br(e($body)) !!}
</x-mail::message>
