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

---

{{ __(':name invited you to the :community community. :operator runs Knowii and sends this email for that community. We got your email address from the person who invited you and use it only for this invitation. If you do nothing, we delete the invitation and your address :period after it expires.', ['name' => $inviterName, 'community' => $communityName, 'operator' => $operator, 'period' => $deletedAfter]) }}@if ($privacyPolicyUrl !== null) {{ __('Your rights are in the privacy policy: :link.', ['link' => $privacyPolicyUrl]) }}@endif

@endcomponent
