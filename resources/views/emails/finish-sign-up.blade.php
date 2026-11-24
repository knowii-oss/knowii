@component('mail::message')
{{ __('Someone asked to create an account on Knowii with this email address. If it was you, open this link to choose your password and finish your sign-up.') }}

@component('mail::button', ['url' => App\Support\MailText::address($url)])
{{ __('Finish my sign-up') }}
@endcomponent

{{ __('The link works once, for :minutes minutes.', ['minutes' => $minutes]) }}

{{ __('If you did not ask for it, ignore this email: no account is created without the link.') }}
@endcomponent
