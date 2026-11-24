@component('mail::message')
{{ App\Support\MailText::plain(__(':name made you the owner of the :community community on Knowii.', ['name' => $formerOwnerName, 'community' => $communityName])) }}

{{ App\Support\MailText::plain(__('As the owner, you can change the details of the community, choose its admins, transfer the ownership and delete the community. :name is now an admin.', ['name' => $formerOwnerName])) }}

@component('mail::button', ['url' => App\Support\MailText::address($settingsUrl)])
{{ __('Open the settings of the community') }}
@endcomponent

{{ __('If you do not want to own this community, you can give it back: transfer the ownership to an admin in the settings.') }}
@endcomponent
