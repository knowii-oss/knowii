@component('mail::message')
{{ __('The email address of your account on Knowii changed to :address. Knowii sends its mail to that address from now on.', ['address' => $address]) }}

@if ($contact !== null)
{{ __('If you did not make this change, contact the administrators of the site at :contact.', ['contact' => $contact]) }}
@else
{{ __('If you did not make this change, contact the administrators of the site.') }}
@endif
@endcomponent
