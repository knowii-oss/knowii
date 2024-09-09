# TODO

- create community pages based on teams pages
- InvitationModel --> replace by concrete model
- rework CommunityController, CommunityMemberController and CommunityInvitationController
- remove mentions to Jetstream membershipModel / membership
- Community classes: replace "mixed" with actual types

- Create a GuestLayout and use it for all auth pages (except Welcome)
- Use GuestFooter in there
- implement Banner component
- add addLink to ApplicationLogo, use it on AuthenticationCard, and remove AuthenticationCardLogo
- rename profile_photo_path from user, and adapt model, factories, etc

use Illuminate\Support\Facades\Log;
Log::emergency("LOL");
dd($response->getStatusCode());
