<?php

namespace App\Providers;

use App\Actions\AccessGroups\AddAccessGroupMember;
use App\Actions\AccessGroups\CreateAccessGroup;
use App\Actions\AccessGroups\DeleteAccessGroup;
use App\Actions\AccessGroups\RemoveAccessGroupMember;
use App\Actions\AccessGroups\UpdateAccessGroup;
use App\Actions\Auth\MagicLinks;
use App\Actions\Chat\CreateChatMessage;
use App\Actions\Chat\DeleteChatMessage;
use App\Actions\Chat\MarkChatAsRead;
use App\Actions\Chat\UpdateChatMessage;
use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\UpdateComment;
use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\Communities\TransferCommunityOwnership;
use App\Actions\Communities\UpdateCommunity;
use App\Actions\Communities\UpdateModeratorPermissions;
use App\Actions\Communities\VerifyCommunityNameAvailability;
use App\Actions\CommunityMembers\AcceptCommunityInvitation;
use App\Actions\CommunityMembers\CancelCommunityInvitation;
use App\Actions\CommunityMembers\InviteCommunityMember;
use App\Actions\CommunityMembers\LeaveCommunity;
use App\Actions\CommunityMembers\RemoveCommunityMember;
use App\Actions\CommunityMembers\UpdateCommunityMemberRole;
use App\Actions\Conversations\MarkConversationAsRead;
use App\Actions\Conversations\SendDirectMessage;
use App\Actions\Conversations\StartConversation;
use App\Actions\EmailOctopus\PruneEmailOctopusContacts;
use App\Actions\Newsletter\SubscribeToNewsletter;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\DeletePage;
use App\Actions\Pages\ReorderPages;
use App\Actions\Pages\TrackLessonProgress;
use App\Actions\Pages\UpdatePage;
use App\Actions\Posts\CreatePost;
use App\Actions\Posts\DeletePost;
use App\Actions\Posts\PinPost;
use App\Actions\Posts\UpdatePost;
use App\Actions\Reactions\ToggleReaction;
use App\Actions\Resources\CaptureResourcePage;
use App\Actions\Resources\CreateCommunityResource;
use App\Actions\Resources\DeleteCommunityResource;
use App\Actions\Resources\UpdateCommunityResource;
use App\Actions\Retention\PruneExpiredData;
use App\Actions\SpaceGroups\CreateSpaceGroup;
use App\Actions\SpaceGroups\DeleteSpaceGroup;
use App\Actions\SpaceGroups\ReorderSpaceGroups;
use App\Actions\SpaceGroups\UpdateSpaceGroup;
use App\Actions\Spaces\CreateSpace;
use App\Actions\Spaces\DeleteSpace;
use App\Actions\Spaces\ReorderSpaces;
use App\Actions\Spaces\UpdateSpace;
use App\Actions\Uploads\UploadImage;
use App\Actions\Users\BlockMember;
use App\Actions\Users\UpdateAvatar;
use App\Actions\Users\UpdateOnlineStatus;
use App\Actions\Users\VerifyUsernameAvailability;
use App\Actions\Waitlist\JoinWaitlist;
use App\Constants;
use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Contracts\AccessGroups\CreatesAccessGroups;
use App\Contracts\AccessGroups\DeletesAccessGroups;
use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Contracts\AccessGroups\UpdatesAccessGroups;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Chat\MarksChatAsRead;
use App\Contracts\Chat\UpdatesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Contracts\Communities\VerifiesCommunityNameAvailability;
use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Contracts\Conversations\MarksConversationsAsRead;
use App\Contracts\Conversations\SendsDirectMessages;
use App\Contracts\Conversations\StartsConversations;
use App\Contracts\Newsletter\SubscribesToNewsletter;
use App\Contracts\Pages\CreatesPages;
use App\Contracts\Pages\DeletesPages;
use App\Contracts\Pages\ReordersPages;
use App\Contracts\Pages\TracksLessonProgress;
use App\Contracts\Pages\UpdatesPages;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Reactions\TogglesReactions;
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
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Uploads\UploadsImages;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\UpdatesAvatars;
use App\Contracts\Users\UpdatesOnlineStatus;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Contracts\Waitlist\JoinsWaitlist;
use App\Models\User;
use App\Support\ReverbPresenceChannels;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // Register singletons implementing contracts (cfr \App\Contracts)
        app()->singleton(VerifiesUsernameAvailability::class, VerifyUsernameAvailability::class);
        app()->singleton(CreatesCommunities::class, CreateCommunity::class);
        app()->singleton(DeletesCommunities::class, DeleteCommunity::class);
        app()->singleton(UpdatesCommunities::class, UpdateCommunity::class);
        app()->singleton(VerifiesCommunityNameAvailability::class, VerifyCommunityNameAvailability::class);
        app()->singleton(UpdatesAvatars::class, UpdateAvatar::class);
        app()->singleton(UpdatesOnlineStatus::class, UpdateOnlineStatus::class);
        app()->singleton(BlocksMembers::class, BlockMember::class);
        app()->singleton(StartsConversations::class, StartConversation::class);
        app()->singleton(SendsDirectMessages::class, SendDirectMessage::class);
        app()->singleton(MarksConversationsAsRead::class, MarkConversationAsRead::class);
        app()->singleton(ReadsPresenceChannels::class, ReverbPresenceChannels::class);
        app()->singleton(CreatesCommunityResources::class, CreateCommunityResource::class);
        app()->bind(CapturesResourcePages::class, CaptureResourcePage::class);
        app()->singleton(UpdatesCommunityResources::class, UpdateCommunityResource::class);
        app()->singleton(DeletesCommunityResources::class, DeleteCommunityResource::class);
        app()->singleton(UpdatesModeratorPermissions::class, UpdateModeratorPermissions::class);
        app()->singleton(TransfersCommunityOwnership::class, TransferCommunityOwnership::class);
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
        app()->singleton(AcceptsCommunityInvitations::class, AcceptCommunityInvitation::class);
        app()->singleton(JoinsWaitlist::class, JoinWaitlist::class);
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
        app()->singleton(UpdatesPosts::class, UpdatePost::class);
        app()->singleton(DeletesPosts::class, DeletePost::class);
        app()->singleton(PinsPosts::class, PinPost::class);
        app()->singleton(CreatesComments::class, CreateComment::class);
        app()->singleton(UpdatesComments::class, UpdateComment::class);
        app()->singleton(DeletesComments::class, DeleteComment::class);
        app()->singleton(TogglesReactions::class, ToggleReaction::class);
        app()->singleton(UploadsImages::class, UploadImage::class);
        app()->singleton(PrunesExpiredData::class, PruneExpiredData::class);
        app()->singleton(PrunesMailingListContacts::class, PruneEmailOctopusContacts::class);

        // Public waitlist endpoint: a few requests per minute and per IP address
        RateLimiter::for('waitlist', static function (Request $request) {
            return Limit::perMinute(Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE)->by($request->ip());
        });

        // Public newsletter endpoint (#938): the same limit as the waitlist
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

        RateLimiter::for('comments', static function (Request $request) {
            return Limit::perMinute(Constants::COMMENTS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('reactions', static function (Request $request) {
            return Limit::perMinute(Constants::REACTIONS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('uploads', static function (Request $request) {
            return Limit::perMinute(Constants::UPLOADS_PER_MINUTE)->by(self::rateLimitKey($request));
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

        RateLimiter::for('name-checks', static function (Request $request) {
            return Limit::perMinute(Constants::NAME_CHECKS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('ownership-transfers', static function (Request $request) {
            return Limit::perMinute(Constants::OWNERSHIP_TRANSFERS_PER_MINUTE)->by(self::rateLimitKey($request));
        });

        RateLimiter::for('markdown-preview', static function (Request $request) {
            return Limit::perMinute(Constants::MARKDOWN_PREVIEWS_PER_MINUTE)->by(self::rateLimitKey($request));
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
