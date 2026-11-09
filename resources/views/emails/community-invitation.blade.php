@component('mail::message')
{{ __('You are invited to join the :community community on Knowii.', ['community' => $communityName]) }}

@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
{{ __('If you do not have an account, create one with this email address first:') }}

@component('mail::button', ['url' => route('register')])
{{ __('Create an account') }}
@endcomponent

{{ __('Then, or if you already have an account with this email address, accept the invitation:') }}
@else
{{ __('Sign in with this email address, then accept the invitation:') }}
@endif

@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept the invitation') }}
@endcomponent

{{ __('The invitation expires on :date. It works only for an account with this email address.', ['date' => $expiresAt->toFormattedDateString()]) }}

{{ __('If you did not expect this invitation, ignore this email.') }}
@endcomponent
