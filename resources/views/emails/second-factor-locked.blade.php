@component('mail::message')
{{ __('The right password of your account on Knowii (or a sign-in link sent to this address) was used, then wrong codes of two-factor authentication were entered :count times. To protect your account, the second step of the sign-in is closed until :until.', ['count' => $count, 'until' => $closedUntil]) }}

{{ __('If it was you, sign in again after that time, with the code of your authenticator app or a recovery code.') }}

{{ __('If it was not you, someone knows your password: change it now.') }}

@component('mail::button', ['url' => App\Support\MailText::address($resetUrl)])
{{ __('Change your password') }}
@endcomponent
@endcomponent
