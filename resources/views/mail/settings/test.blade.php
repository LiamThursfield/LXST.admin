<x-mail::message>
# {{ __('site_settings.mail.test_mail.heading') }}

{{ __('site_settings.mail.test_mail.body', ['site' => $siteName, 'mailer' => $mailerName]) }}
</x-mail::message>
