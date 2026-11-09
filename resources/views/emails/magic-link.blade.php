@component('mail::message')
{{ __('Use this link to sign in to Knowii.') }}

@component('mail::button', ['url' => $url])
{{ __('Sign in') }}
@endcomponent

{{ __('The link works once, for :minutes minutes.', ['minutes' => $minutes]) }}

{{ __('If you did not ask for it, ignore this email: nobody can sign in without the link.') }}
@endcomponent
