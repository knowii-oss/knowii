<?php

namespace App\Providers;

use App\Actions\AccessGroups\AddAccessGroupMember;
use App\Actions\AccessGroups\CreateAccessGroup;
use App\Actions\AccessGroups\DeleteAccessGroup;
use App\Actions\AccessGroups\RemoveAccessGroupMember;
use App\Actions\AccessGroups\SetAccessGroupRules;
use App\Actions\AccessGroups\SetAccessGroupSpaceGroups;
use App\Actions\AccessGroups\UpdateAccessGroup;
use App\Actions\Auth\ConfirmAccountDeletion;
use App\Actions\Auth\EntitlementsClient;
use App\Actions\Auth\LinkIdentity;
use App\Actions\Auth\MagicLinks;
use App\Actions\Auth\OpenIdConnectClient;
use App\Actions\Auth\SignInWithOpenIdConnect;
use App\Actions\Auth\TakeOverUnverifiedAccount;
use App\Actions\Auth\UnlinkIdentity;
use App\Actions\Chat\CreateChatMessage;
use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\MarkChatAsRead;
use App\Actions\Chat\UpdateChatMessage;
use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\UpdateComment;
use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\Communities\ExportCommunity;
use App\Actions\Communities\TransferCommunityOwnership;
use App\Actions\Communities\UpdateCommunity;
use App\Actions\Communities\UpdateCommunityIndexing;
use App\Actions\Communities\UpdateCommunityJoinPolicy;
use App\Actions\Communities\UpdateModeratorPermissions;
use App\Actions\Communities\VerifyCommunityNameAvailability;
use App\Actions\CommunityMembers\AcceptCommunityInvitation;
use App\Actions\CommunityMembers\CancelCommunityInvitation;
use App\Actions\CommunityMembers\InviteCommunityMember;
use App\Actions\CommunityMembers\JoinCommunity;
use App\Actions\CommunityMembers\LeaveCommunity;
use App\Actions\CommunityMembers\LiftCommunityJoinBlock;
use App\Actions\CommunityMembers\RemoveCommunityMember;
use App\Actions\CommunityMembers\UpdateCommunityMemberRole;
use App\Actions\Content\LeaveMarkers;
use App\Actions\Content\RenderContentAgain;
use App\Actions\Conversations\MarkConversationAsRead;
use App\Actions\Conversations\SendDirectMessage;
use App\Actions\Conversations\StartConversation;
use App\Actions\CourseMaterials\CreateCourseMaterial;
use App\Actions\CourseMaterials\DeleteCourseMaterial;
use App\Actions\CourseMaterials\ReorderCourseMaterials;
use App\Actions\CourseMaterials\UpdateCourseMaterial;
use App\Actions\DeleteUser;
use App\Actions\EmailOctopus\PruneEmailOctopusContacts;
use App\Actions\Entitlements\ReceiveEntitlementEvent;
use App\Actions\Entitlements\RefreshEntitlements;
use App\Actions\Entitlements\SyncEntitlementRules;
use App\Actions\Events\AnswerEvent;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\DuplicateEvent;
use App\Actions\Events\SetEventState;
use App\Actions\Events\UpdateEvent;
use App\Actions\InvitationImports\ConfirmInvitationImport;
use App\Actions\InvitationImports\CreateInvitationImport;
use App\Actions\InvitationImports\DeleteInvitationImport;
use App\Actions\InvitationImports\ResendInvitationImport;
use App\Actions\InvitationImports\SendInvitationImportMails;
use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\InviteLinks\DeleteInviteLink;
use App\Actions\InviteLinks\JoinCommunityThroughInviteLink;
use App\Actions\InviteLinks\UpdateInviteLink;
use App\Actions\Mail\CheckMailBudget;
use App\Actions\Mail\RecordSentEmails;
use App\Actions\Mail\ResendMailUsage;
use App\Actions\MemberTags\AddMemberTagMember;
use App\Actions\MemberTags\CreateMemberTag;
use App\Actions\MemberTags\DeleteMemberTag;
use App\Actions\MemberTags\RemoveMemberTagMember;
use App\Actions\MemberTags\UpdateMemberTag;
use App\Actions\Newsletter\SubscribeToNewsletter;
use App\Actions\Notifications\BuildCatchUp;
use App\Actions\Notifications\ChooseSpaceLevels;
use App\Actions\Notifications\FollowThreads;
use App\Actions\Notifications\ListNotifications;
use App\Actions\Notifications\MarkNotificationsRead;
use App\Actions\Notifications\MarkNotificationsSeen;
use App\Actions\Notifications\Notify;
use App\Actions\Notifications\PruneNotifications;
use App\Actions\Notifications\TrackSpaceVisits;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\DeletePage;
use App\Actions\Pages\ReorderPages;
use App\Actions\Pages\TrackLessonProgress;
use App\Actions\Pages\UpdatePage;
use App\Actions\Posts\CreatePost;
use App\Actions\Posts\DeletePost;
use App\Actions\Posts\PinPost;
use App\Actions\Posts\UpdatePost;
use App\Actions\Quizzes\AttemptQuiz;
use App\Actions\Quizzes\DeleteQuiz;
use App\Actions\Quizzes\SaveQuiz;
use App\Actions\Reactions\ToggleReaction;
use App\Actions\Resources\CaptureResourcePage;
use App\Actions\Resources\CreateCommunityResource;
use App\Actions\Resources\DeleteCommunityResource;
use App\Actions\Resources\UpdateCommunityResource;
use App\Actions\Retention\PruneExpiredData;
use App\Actions\SpaceGroups\CreateSpaceGroup;
use App\Actions\SpaceGroups\DeleteSpaceGroup;
use App\Actions\SpaceGroups\ReorderSpaceGroups;
use App\Actions\SpaceGroups\SetSpaceGroupAccessGroups;
use App\Actions\SpaceGroups\UpdateSpaceGroup;
use App\Actions\Spaces\CreateSpace;
use App\Actions\Spaces\DeleteSpace;
use App\Actions\Spaces\ReorderSpaces;
use App\Actions\Spaces\UpdateSpace;
use App\Actions\Uploads\BuildUploadSizes;
use App\Actions\Uploads\UploadImage;
use App\Actions\Users\BlockMember;
use App\Actions\Users\ChangeEmailAddress;
use App\Actions\Users\DeleteMemberContent;
use App\Actions\Users\ExportUser;
use App\Actions\Users\RecordLastVisit;
use App\Actions\Users\UpdateAvatar;
use App\Actions\Users\UpdateMemberListVisibility;
use App\Actions\Users\UpdateNotificationSettings;
use App\Actions\Users\UpdateOnlineStatus;
use App\Actions\Users\UpdateTimeZone;
use App\Actions\Users\VerifyUsernameAvailability;
use App\ApiRoutes;
use App\Constants;
use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Contracts\AccessGroups\CreatesAccessGroups;
use App\Contracts\AccessGroups\DeletesAccessGroups;
use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Contracts\AccessGroups\SetsAccessGroupRules;
use App\Contracts\AccessGroups\SetsAccessGroupSpaceGroups;
use App\Contracts\AccessGroups\UpdatesAccessGroups;
use App\Contracts\Auth\ConfirmsAccountDeletions;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\LinksIdentities;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Auth\ReadsSubjectEntitlements;
use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\Auth\SignsInWithOpenIdConnect;
use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Contracts\Auth\UnlinksIdentities;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Chat\MarksChatAsRead;
use App\Contracts\Chat\UpdatesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\ExportsCommunities;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\UpdatesCommunityIndexing;
use App\Contracts\Communities\UpdatesCommunityJoinPolicy;
use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Contracts\Communities\VerifiesCommunityNameAvailability;
use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\CommunityMembers\JoinsCommunities;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\LiftsCommunityJoinBlocks;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Contracts\Content\LeavesMarkers;
use App\Contracts\Content\RendersContentAgain;
use App\Contracts\Conversations\MarksConversationsAsRead;
use App\Contracts\Conversations\SendsDirectMessages;
use App\Contracts\Conversations\StartsConversations;
use App\Contracts\CourseMaterials\CreatesCourseMaterials;
use App\Contracts\CourseMaterials\DeletesCourseMaterials;
use App\Contracts\CourseMaterials\ReordersCourseMaterials;
use App\Contracts\CourseMaterials\UpdatesCourseMaterials;
use App\Contracts\Entitlements\ReceivesEntitlementEvents;
use App\Contracts\Entitlements\RefreshesEntitlements;
use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Contracts\Events\AnswersEvents;
use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\DeletesEvents;
use App\Contracts\Events\DuplicatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\InvitationImports\ConfirmsInvitationImports;
use App\Contracts\InvitationImports\CreatesInvitationImports;
use App\Contracts\InvitationImports\DeletesInvitationImports;
use App\Contracts\InvitationImports\ResendsInvitationImports;
use App\Contracts\InvitationImports\SendsInvitationImportMails;
use App\Contracts\InviteLinks\CreatesInviteLinks;
use App\Contracts\InviteLinks\DeletesInviteLinks;
use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Contracts\InviteLinks\UpdatesInviteLinks;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Mail\ReadsMailUsage;
use App\Contracts\Mail\RecordsSentEmails;
use App\Contracts\MemberTags\AddsMemberTagMembers;
use App\Contracts\MemberTags\CreatesMemberTags;
use App\Contracts\MemberTags\DeletesMemberTags;
use App\Contracts\MemberTags\RemovesMemberTagMembers;
use App\Contracts\MemberTags\UpdatesMemberTags;
use App\Contracts\Newsletter\SubscribesToNewsletter;
use App\Contracts\Notifications\BuildsCatchUp;
use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\ListsNotifications;
use App\Contracts\Notifications\MarksNotificationsRead;
use App\Contracts\Notifications\MarksNotificationsSeen;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\PrunesNotifications;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Contracts\Pages\CreatesPages;
use App\Contracts\Pages\DeletesPages;
use App\Contracts\Pages\ReordersPages;
use App\Contracts\Pages\TracksLessonProgress;
use App\Contracts\Pages\UpdatesPages;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Quizzes\AttemptsQuizzes;
use App\Contracts\Quizzes\DeletesQuizzes;
use App\Contracts\Quizzes\SavesQuizzes;
use App\Contracts\Reactions\TogglesReactions;
use App\Contracts\Realtime\EndsLiveConnections;
use App\Contracts\Realtime\ReadsPresenceChannels;
use App\Contracts\Resources\CapturesResourcePages;
use App\Contracts\Resources\CreatesCommunityResources;
use App\Contracts\Resources\DeletesCommunityResources;
use App\Contracts\Resources\UpdatesCommunityResources;
use App\Contracts\Retention\PrunesExpiredData;
use App\Contracts\Retention\PrunesMailingListContacts;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Contracts\SpaceGroups\SetsSpaceGroupAccessGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Uploads\BuildsUploadSizes;
use App\Contracts\Uploads\UploadsImages;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\DeletesMemberContent;
use App\Contracts\Users\ExportsUsers;
use App\Contracts\Users\RecordsLastVisits;
use App\Contracts\Users\UpdatesAvatars;
use App\Contracts\Users\UpdatesMemberListVisibility;
use App\Contracts\Users\UpdatesNotificationSettings;
use App\Contracts\Users\UpdatesOnlineStatus;
use App\Contracts\Users\UpdatesTimeZones;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Http\Controllers\Inertia\MemberController;
use App\Http\Middleware\StartSessionWithoutTokenAddresses;
use App\Models\User;
use App\Support\EmailChangeBudget;
use App\Support\MailSettings;
use App\Support\MemberTagsOfMembers;
use App\Support\ReverbLiveConnections;
use App\Support\ReverbPresenceChannels;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        // The session middleware keeps no address that holds a link token or a signature as the previous address (ADR 0062,
        // ADR 0064). Bound in place of the middleware of the framework, built as the framework builds it (one instance, with
        // the cache that blocks a session), so that the web group and the first-party requests of the API use it, and the
        // middleware keeps its name and its place in the order of the middleware
        $this->app->singleton(StartSession::class, static fn (Application $app): StartSession => new StartSessionWithoutTokenAddresses(
            $app->make(SessionManager::class),
            static fn (): CacheFactory => $app->make(CacheFactory::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The reverse proxy in front (SELF_HOSTING.md): without a trusted proxy, Laravel sees the proxy address and the http scheme
        $trustedProxies = config()->string('app.trusted_proxies');
        if (trim($trustedProxies) !== '') {
            TrustProxies::at(trim($trustedProxies) === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }

        // From a trusted proxy, the application reads the address of the client, the host name, the port and the scheme, and
        // no other value. The list holds for every way to trust a proxy, so it is set also when TRUSTED_PROXIES is empty
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

        // Register singletons implementing contracts (cfr \App\Contracts)
        app()->singleton(VerifiesUsernameAvailability::class, VerifyUsernameAvailability::class);
        app()->singleton(CreatesCommunities::class, CreateCommunity::class);
        app()->singleton(DeletesCommunities::class, DeleteCommunity::class);
        app()->singleton(UpdatesCommunities::class, UpdateCommunity::class);
        app()->singleton(VerifiesCommunityNameAvailability::class, VerifyCommunityNameAvailability::class);
        app()->singleton(UpdatesAvatars::class, UpdateAvatar::class);
        app()->singleton(UpdatesOnlineStatus::class, UpdateOnlineStatus::class);
        app()->singleton(UpdatesMemberListVisibility::class, UpdateMemberListVisibility::class);
        app()->singleton(BlocksMembers::class, BlockMember::class);
        app()->singleton(DeletesMemberContent::class, DeleteMemberContent::class);
        app()->singleton(DeletesAccounts::class, DeleteUser::class);
        app()->singleton(ExportsUsers::class, ExportUser::class);
        app()->singleton(StartsConversations::class, StartConversation::class);
        app()->singleton(SendsDirectMessages::class, SendDirectMessage::class);
        app()->singleton(MarksConversationsAsRead::class, MarkConversationAsRead::class);
        app()->singleton(ReadsPresenceChannels::class, ReverbPresenceChannels::class);
        // A takeover ends the live connections of the account (ADR 0059)
        app()->singleton(EndsLiveConnections::class, ReverbLiveConnections::class);
        app()->singleton(CreatesCommunityResources::class, CreateCommunityResource::class);
        app()->bind(CapturesResourcePages::class, CaptureResourcePage::class);
        app()->singleton(UpdatesCommunityResources::class, UpdateCommunityResource::class);
        app()->singleton(DeletesCommunityResources::class, DeleteCommunityResource::class);
        app()->singleton(UpdatesModeratorPermissions::class, UpdateModeratorPermissions::class);
        app()->singleton(UpdatesCommunityJoinPolicy::class, UpdateCommunityJoinPolicy::class);
        app()->singleton(UpdatesCommunityIndexing::class, UpdateCommunityIndexing::class);
        app()->singleton(JoinsCommunities::class, JoinCommunity::class);
        app()->singleton(LiftsCommunityJoinBlocks::class, LiftCommunityJoinBlock::class);
        app()->singleton(TransfersCommunityOwnership::class, TransferCommunityOwnership::class);
        app()->singleton(ExportsCommunities::class, ExportCommunity::class);
        app()->singleton(SendsMagicLinks::class, MagicLinks::class);
        app()->singleton(ConsumesMagicLinks::class, MagicLinks::class);
        app()->singleton(UpdatesCommunityMemberRoles::class, UpdateCommunityMemberRole::class);
        app()->singleton(RemovesCommunityMembers::class, RemoveCommunityMember::class);
        app()->singleton(LeavesCommunities::class, LeaveCommunity::class);
        app()->singleton(InvitesCommunityMembers::class, InviteCommunityMember::class);
        app()->singleton(CancelsCommunityInvitations::class, CancelCommunityInvitation::class);
        app()->singleton(CreatesAccessGroups::class, CreateAccessGroup::class);
        app()->singleton(UpdatesAccessGroups::class, UpdateAccessGroup::class);
        app()->singleton(DeletesAccessGroups::class, DeleteAccessGroup::class);
        app()->singleton(AddsAccessGroupMembers::class, AddAccessGroupMember::class);
        app()->singleton(RemovesAccessGroupMembers::class, RemoveAccessGroupMember::class);
        // Invitations from a file (#1390, ADR 0061)
        app()->singleton(CreatesInvitationImports::class, CreateInvitationImport::class);
        app()->singleton(ConfirmsInvitationImports::class, ConfirmInvitationImport::class);
        app()->singleton(ResendsInvitationImports::class, ResendInvitationImport::class);
        app()->singleton(DeletesInvitationImports::class, DeleteInvitationImport::class);
        app()->singleton(SendsInvitationImportMails::class, SendInvitationImportMails::class);
        // Invite links (#1391, ADR 0062)
        app()->singleton(CreatesInviteLinks::class, CreateInviteLink::class);
        app()->singleton(UpdatesInviteLinks::class, UpdateInviteLink::class);
        app()->singleton(DeletesInviteLinks::class, DeleteInviteLink::class);
        app()->singleton(JoinsCommunitiesThroughInviteLinks::class, JoinCommunityThroughInviteLink::class);
        // Member tags (#1389, ADR 0060)
        app()->singleton(CreatesMemberTags::class, CreateMemberTag::class);
        app()->singleton(UpdatesMemberTags::class, UpdateMemberTag::class);
        app()->singleton(DeletesMemberTags::class, DeleteMemberTag::class);
        app()->singleton(AddsMemberTagMembers::class, AddMemberTagMember::class);
        app()->singleton(RemovesMemberTagMembers::class, RemoveMemberTagMember::class);
        // A tag that the reader may not see answers 404 before the token abilities and the controller (here, not in a route
        // file: the cached routes do not keep a binding)
        Route::bind(ApiRoutes::PARAM_MEMBER_TAG, MemberTagsOfMembers::bindRoute(...));
        app()->singleton(SetsAccessGroupSpaceGroups::class, SetAccessGroupSpaceGroups::class);
        app()->singleton(SetsSpaceGroupAccessGroups::class, SetSpaceGroupAccessGroups::class);
        app()->singleton(AcceptsCommunityInvitations::class, AcceptCommunityInvitation::class);
        app()->singleton(SubscribesToNewsletter::class, SubscribeToNewsletter::class);
        app()->singleton(CreatesSpaceGroups::class, CreateSpaceGroup::class);
        app()->singleton(UpdatesSpaceGroups::class, UpdateSpaceGroup::class);
        app()->singleton(DeletesSpaceGroups::class, DeleteSpaceGroup::class);
        app()->singleton(ReordersSpaceGroups::class, ReorderSpaceGroups::class);
        app()->singleton(CreatesSpaces::class, CreateSpace::class);
        app()->singleton(UpdatesSpaces::class, UpdateSpace::class);
        app()->singleton(DeletesSpaces::class, DeleteSpace::class);
        app()->singleton(ReordersSpaces::class, ReorderSpaces::class);
        app()->singleton(CreatesPosts::class, CreatePost::class);
        app()->singleton(CreatesChatMessages::class, CreateChatMessage::class);
        app()->singleton(UpdatesChatMessages::class, UpdateChatMessage::class);
        app()->singleton(DeletesChatMessages::class, DeleteChatMessage::class);
        app()->singleton(MarksChatAsRead::class, MarkChatAsRead::class);
        app()->singleton(CreatesPages::class, CreatePage::class);
        app()->singleton(UpdatesPages::class, UpdatePage::class);
        app()->singleton(DeletesPages::class, DeletePage::class);
        app()->singleton(ReordersPages::class, ReorderPages::class);
        app()->singleton(TracksLessonProgress::class, TrackLessonProgress::class);
        // Events of events spaces (#1513, ADR 0053)
        app()->singleton(CreatesEvents::class, CreateEvent::class);
        app()->singleton(UpdatesEvents::class, UpdateEvent::class);
        app()->singleton(SetsEventStates::class, SetEventState::class);
        app()->singleton(DeletesEvents::class, DeleteEvent::class);
        app()->singleton(DuplicatesEvents::class, DuplicateEvent::class);
        // The answers of members to events (#1515, ADR 0053)
        app()->singleton(AnswersEvents::class, AnswerEvent::class);
        app()->singleton(UpdatesPosts::class, UpdatePost::class);
        app()->singleton(DeletesPosts::class, DeletePost::class);
        app()->singleton(PinsPosts::class, PinPost::class);
        app()->singleton(CreatesComments::class, CreateComment::class);
        app()->singleton(UpdatesComments::class, UpdateComment::class);
        app()->singleton(DeletesComments::class, DeleteComment::class);
        app()->singleton(TogglesReactions::class, ToggleReaction::class);
        app()->singleton(UploadsImages::class, UploadImage::class);
        app()->singleton(BuildsUploadSizes::class, BuildUploadSizes::class);
        app()->singleton(RendersContentAgain::class, RenderContentAgain::class);
        app()->singleton(LeavesMarkers::class, LeaveMarkers::class);
        app()->singleton(PrunesExpiredData::class, PruneExpiredData::class);
        // Course materials (#1336)
        app()->singleton(CreatesCourseMaterials::class, CreateCourseMaterial::class);
        app()->singleton(UpdatesCourseMaterials::class, UpdateCourseMaterial::class);
        app()->singleton(DeletesCourseMaterials::class, DeleteCourseMaterial::class);
        app()->singleton(ReordersCourseMaterials::class, ReorderCourseMaterials::class);
        // Lesson quizzes (#1335)
        app()->singleton(SavesQuizzes::class, SaveQuiz::class);
        app()->singleton(DeletesQuizzes::class, DeleteQuiz::class);
        app()->singleton(AttemptsQuizzes::class, AttemptQuiz::class);
        app()->singleton(PrunesMailingListContacts::class, PruneEmailOctopusContacts::class);
        // Notifications (#1474, ADR 0042)
        app()->singleton(Notifies::class, Notify::class);
        app()->singleton(ListsNotifications::class, ListNotifications::class);
        app()->singleton(MarksNotificationsSeen::class, MarkNotificationsSeen::class);
        app()->singleton(MarksNotificationsRead::class, MarkNotificationsRead::class);
        app()->singleton(PrunesNotifications::class, PruneNotifications::class);
        app()->singleton(FollowsThreads::class, FollowThreads::class);
        app()->singleton(ChoosesSpaceLevels::class, ChooseSpaceLevels::class);
        // Where I am (#1476, ADR 0048)
        app()->singleton(TracksSpaceVisits::class, TrackSpaceVisits::class);
        app()->singleton(BuildsCatchUp::class, BuildCatchUp::class);
        app()->singleton(UpdatesTimeZones::class, UpdateTimeZone::class);
        // A change of the email address applies after the new address is confirmed (#1706, ADR 0058)
        app()->singleton(ChangesEmailAddresses::class, ChangeEmailAddress::class);
        app()->singleton(UpdatesNotificationSettings::class, UpdateNotificationSettings::class);
        app()->singleton(RecordsLastVisits::class, RecordLastVisit::class);
        // Sign in with an OpenID Connect provider (#1385, ADR 0045)
        app()->singleton(ReadsOpenIdConnectIdentities::class, OpenIdConnectClient::class);
        app()->singleton(SignsInWithOpenIdConnect::class, SignInWithOpenIdConnect::class);
        app()->singleton(LinksIdentities::class, LinkIdentity::class);
        app()->singleton(UnlinksIdentities::class, UnlinkIdentity::class);
        app()->singleton(TakesOverUnverifiedAccounts::class, TakeOverUnverifiedAccount::class);
        // An account without a password confirms its deletion with a new sign-in (#1521, ADR 0052)
        app()->singleton(ConfirmsAccountDeletions::class, ConfirmAccountDeletion::class);
        // The entitlements of the sign-in provider feed the access groups (#1386, ADR 0047)
        app()->singleton(ReadsSubjectEntitlements::class, EntitlementsClient::class);
        app()->singleton(RefreshesEntitlements::class, RefreshEntitlements::class);
        app()->singleton(SyncsEntitlementRules::class, SyncEntitlementRules::class);
        app()->singleton(ReceivesEntitlementEvents::class, ReceiveEntitlementEvent::class);
        app()->singleton(SetsAccessGroupRules::class, SetAccessGroupRules::class);
        // Email delivery (#1477, ADR 0054): the count of sent mail, the usage of the provider and the budget of the month
        app()->singleton(RecordsSentEmails::class, RecordSentEmails::class);
        app()->singleton(ReadsMailUsage::class, ResendMailUsage::class);
        app()->singleton(ChecksMailBudgets::class, CheckMailBudget::class);

        // Public newsletter endpoint (#938): a few requests per minute and per IP address
        RateLimiter::for('newsletter', static function (Request $request) {
            return Limit::perMinute(Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE)->by($request->ip());
        });

        // "Capture again" (ADR 0018): each capture fetches a site
        RateLimiter::for('captures', static function (Request $request) {
            return Limit::perMinute(Constants::CAPTURES_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // Chat messages (#1185): a burst is normal in a chat, a flood is not
        RateLimiter::for('messages', static function (Request $request) {
            return Limit::perMinute(Constants::CHAT_MESSAGES_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('posts', static function (Request $request) {
            return Limit::perMinute(Constants::POSTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('events', static function (Request $request) {
            return Limit::perMinute(Constants::EVENTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // Answers to events (#1515): a member changes their mind a few times, not hundreds of times
        RateLimiter::for('event-answers', static function (Request $request) {
            return Limit::perMinute(Constants::EVENT_ANSWERS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The calendar files of events (#1515): per member, like the answers
        RateLimiter::for('calendar-files', static function (Request $request) {
            return Limit::perMinute(Constants::CALENDAR_FILES_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('comments', static function (Request $request) {
            return Limit::perMinute(Constants::COMMENTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('reactions', static function (Request $request) {
            return Limit::perMinute(Constants::REACTIONS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('uploads', static function (Request $request) {
            return Limit::perMinute(Constants::UPLOADS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // Each start or end of a sign-in with the OpenID Connect provider calls it (#1385, ADR 0045): per IP address
        RateLimiter::for('oidc', static function (Request $request) {
            return Limit::perMinute(Constants::OIDC_REQUESTS_PER_MINUTE)->by('oidc:'.$request->ip());
        });

        // The change calls of the sign-in provider (#1386, ADR 0047): per IP address
        RateLimiter::for('oidc-entitlements', static function (Request $request) {
            return Limit::perMinute(Constants::OIDC_ENTITLEMENT_EVENTS_PER_MINUTE)->by('oidc-entitlements:'.$request->ip());
        });

        // Each sign-in link sends an email (#703): per address and IP, like the login
        RateLimiter::for('magic-link', static function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(mb_strtolower(is_string($email) ? $email : '').'|'.$request->ip());
        });

        // Each invitation sends an email (#1242)
        RateLimiter::for('invitations', static function (Request $request) {
            return Limit::perMinute(Constants::INVITATIONS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The upload, the confirm and the send again of an import of invitations from a file (#1390, ADR 0061)
        RateLimiter::for('invitation-imports', static function (Request $request) {
            return Limit::perMinute(Constants::INVITATION_IMPORTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('name-checks', static function (Request $request) {
            return Limit::perMinute(Constants::NAME_CHECKS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // A person joins an open community (#1486)
        RateLimiter::for('joins', static function (Request $request) {
            return Limit::perMinute(Constants::JOINS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The reads of an invite link (#1391, ADR 0062): its page and the API read of the person, per account or per IP address
        // of a visitor. The join itself uses `joins`
        RateLimiter::for('invite-links', static function (Request $request) {
            return Limit::perMinute(Constants::INVITE_LINK_READS_PER_MINUTE)->by(self::rateLimitKey($request));
        });
        // The address of an invite link, which sends to its page: a limiter of its own, so that one opening counts once here
        // and once in `invite-links`
        RateLimiter::for('invite-link-openings', static function (Request $request) {
            return Limit::perMinute(Constants::INVITE_LINK_READS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('ownership-transfers', static function (Request $request) {
            return Limit::perMinute(Constants::OWNERSHIP_TRANSFERS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The deletion of the own account through the API checks the password (#1492)
        RateLimiter::for('account-deletions', static function (Request $request) {
            return Limit::perMinute(Constants::ACCOUNT_DELETIONS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The link of a change of the email address (#1706, ADR 0058): send it again, cancel the change, open the link. One
        // budget for the web and the API, which the profile form shares for each change of the address: a new link is a mail
        RateLimiter::for(EmailChangeBudget::LIMITER, static fn (Request $request) => EmailChangeBudget::limit($request));

        // Quiz attempts (#1335): the server grades each one
        RateLimiter::for('quiz-attempts', static function (Request $request) {
            return Limit::perMinute(Constants::QUIZ_ATTEMPTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The API reads that visitors may call (#1387, ADR 0044): per IP address, for requests without an account. A signed-in
        // request keeps the behaviour that it had before these routes accepted visitors
        RateLimiter::for('public-reads', static function (Request $request) {
            return $request->user() instanceof User
                ? Limit::none()
                : Limit::perMinute(Constants::PUBLIC_READS_PER_MINUTE)->by('ip:'.$request->ip());
        });

        // Notification mail (#1477, ADR 0054): job middleware of App\Mail\NotificationMail. One key for every mail worker, so
        // that two workers share the count. The closure gets the job, not a request
        RateLimiter::for('mail', static function (object $job) {
            $perSecond = MailSettings::ratePerSecond();

            return $perSecond === 0 ? Limit::none() : Limit::perSecond($perSecond)->by('mail');
        });

        RateLimiter::for('markdown-preview', static function (Request $request) {
            return Limit::perMinute(Constants::MARKDOWN_PREVIEWS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        // The member directory and the API list of the members (#148, ADR 0056): one budget for both, apart from the one of
        // the Markdown preview. A request is counted after its answer, and an answer of the directory that read no member
        // list (a reload for another prop of the page) is not counted. A request over the limit answers 429 in every case
        RateLimiter::for('member-lists', static function (Request $request) {
            return Limit::perMinute(Constants::MEMBER_LISTS_PER_MINUTE)
                ->by(self::rateLimitKey($request))
                ->after(static fn (): bool => $request->attributes->get(MemberController::NO_MEMBER_LIST_ATTRIBUTE) !== true);
        });

        // Enable strict mode during development
        Model::shouldBeStrict(! app()->isProduction());

        // Always prevent lazy loading
        Model::preventLazyLoading();

        // But in production, log the violation instead of throwing an exception.
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                $class = get_class($model);

                info("Attempted lazy loading [{$relation}] on model [{$class}].");
            });
        }
    }

    /**
     * Rate limits count per member, or per IP address for visitors.
     */
    private static function rateLimitKey(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? 'user:'.$user->id : 'ip:'.$request->ip();
    }
}
