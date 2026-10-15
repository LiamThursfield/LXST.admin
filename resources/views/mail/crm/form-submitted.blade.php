<x-mail::message>
# {{ __('crm.submissions.mail.heading', ['form' => $formName]) }}

{{ __('crm.submissions.mail.intro', ['form' => $formName, 'date' => $submittedAt]) }}

<x-mail::table>
| {{ __('crm.submissions.mail.question') }} | {{ __('crm.submissions.mail.answer') }} |
| :-- | :-- |
@foreach ($answers as $answer)
| {{ $answer['label'] }} | {{ str_replace(["\r\n", "\n", '|'], [' ', ' ', '\|'], $answer['value']) }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$submissionUrl">
{{ __('crm.submissions.mail.view') }}
</x-mail::button>
</x-mail::message>
