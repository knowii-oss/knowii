@component('mail::message')
{{ __('Open this link to use this email address for your account on Knowii.') }}

@component('mail::button', ['url' => App\Support\MailText::address($url)])
{{ __('Confirm your new email address') }}
@endcomponent

{{ __('The link works for :minutes minutes.', ['minutes' => $minutes]) }}
@endcomponent
