@component('mail::message')
{{ __('Someone asked to create an account on Knowii with this email address. This address already has an account.') }}

{{ __('If it was you, sign in. If you forgot your password, reset it, or ask for a sign-in link on the sign-in page.') }}

@component('mail::button', ['url' => $signInUrl])
{{ __('Sign in') }}
@endcomponent

[{{ __('Reset my password') }}]({{ $resetUrl }})

{{ __('If it was not you, ignore this email: nothing changed in your account.') }}
@endcomponent
