@component('mail::message')
# {{ __('Email budget of :month', ['month' => $monthName]) }}

{{ __('This month the platform sent :used of :quota emails (:percent%).', ['used' => $usedText, 'quota' => $quotaText, 'percent' => $percentUsed]) }}
@if ($pausedUntil !== null)
{{ __('Notification emails are paused until :date.', ['date' => $pausedUntil]) }}
@else
{{ __('Notification emails stop at :percent%.', ['percent' => $stopPercent]) }}
@endif
{{ __('Sign-in links and other account emails keep going.') }}

@if ($fromProvider)
{{ __('The numbers come from your mail provider.') }}
@else
{{ __('The numbers come from the count of the emails that this instance sent.') }}
@endif
@endcomponent
