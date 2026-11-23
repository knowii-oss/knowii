{{--
    The footer of a notification email (#1478, ADR 0054, design 5.6 point 7): the stop link of the group of the mail (the
    same address as its List-Unsubscribe header), the settings, then the operator and its postal address. The words are the
    ones that the owner approved. `plain` gives the plain-text part, in the same order, each address on its own line after
    its label. NotificationMail::footerData() gives the values; the reason line ("You got this because ...") comes with
    slice 4c.
--}}
@if ($plain ?? false)
{{-- Plain text: no HTML escape --}}
Stop emails like this: {!! $stopUrl !!}
Settings: {!! $settingsUrl !!}
{!! $operatorLine !!}
@else
<ul>
    <li><a href="{{ $stopUrl }}">Stop emails like this</a></li>
    <li><a href="{{ $settingsUrl }}">Settings</a></li>
</ul>
<p>{{ $operatorLine }}</p>
@endif
