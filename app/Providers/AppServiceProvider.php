<?php

namespace App\Providers;

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\UpdateComment;
use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\Communities\UpdateCommunity;
use App\Actions\Communities\UpdateModeratorPermissions;
use App\Actions\CommunityMembers\AcceptCommunityInvitation;
use App\Actions\CommunityMembers\CancelCommunityInvitation;
use App\Actions\CommunityMembers\InviteCommunityMember;
use App\Actions\CommunityMembers\RemoveCommunityMember;
use App\Actions\CommunityMembers\UpdateCommunityMemberRole;
use App\Actions\Posts\CreatePost;
use App\Actions\Posts\DeletePost;
use App\Actions\Posts\PinPost;
use App\Actions\Posts\UpdatePost;
use App\Actions\Reactions\ToggleReaction;
use App\Actions\Resources\CreateTextResource;
use App\Actions\SpaceGroups\CreateSpaceGroup;
use App\Actions\SpaceGroups\DeleteSpaceGroup;
use App\Actions\SpaceGroups\ReorderSpaceGroups;
use App\Actions\SpaceGroups\UpdateSpaceGroup;
use App\Actions\Spaces\CreateSpace;
use App\Actions\Spaces\DeleteSpace;
use App\Actions\Spaces\ReorderSpaces;
use App\Actions\Spaces\UpdateSpace;
use App\Actions\Uploads\UploadImage;
use App\Actions\Users\VerifyUsernameAvailability;
use App\Actions\Waitlist\JoinWaitlist;
use App\Constants;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Reactions\TogglesReactions;
use App\Contracts\Resources\CreatesTextResources;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Uploads\UploadsImages;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Contracts\Waitlist\JoinsWaitlist;
use App\Models\User;
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
        app()->singleton(CreatesTextResources::class, CreateTextResource::class);
        app()->singleton(UpdatesModeratorPermissions::class, UpdateModeratorPermissions::class);
        app()->singleton(UpdatesCommunityMemberRoles::class, UpdateCommunityMemberRole::class);
        app()->singleton(RemovesCommunityMembers::class, RemoveCommunityMember::class);
        app()->singleton(InvitesCommunityMembers::class, InviteCommunityMember::class);
        app()->singleton(CancelsCommunityInvitations::class, CancelCommunityInvitation::class);
        app()->singleton(AcceptsCommunityInvitations::class, AcceptCommunityInvitation::class);
        app()->singleton(JoinsWaitlist::class, JoinWaitlist::class);
        app()->singleton(CreatesSpaceGroups::class, CreateSpaceGroup::class);
        app()->singleton(UpdatesSpaceGroups::class, UpdateSpaceGroup::class);
        app()->singleton(DeletesSpaceGroups::class, DeleteSpaceGroup::class);
        app()->singleton(ReordersSpaceGroups::class, ReorderSpaceGroups::class);
        app()->singleton(CreatesSpaces::class, CreateSpace::class);
        app()->singleton(UpdatesSpaces::class, UpdateSpace::class);
        app()->singleton(DeletesSpaces::class, DeleteSpace::class);
        app()->singleton(ReordersSpaces::class, ReorderSpaces::class);
        app()->singleton(CreatesPosts::class, CreatePost::class);
        app()->singleton(UpdatesPosts::class, UpdatePost::class);
        app()->singleton(DeletesPosts::class, DeletePost::class);
        app()->singleton(PinsPosts::class, PinPost::class);
        app()->singleton(CreatesComments::class, CreateComment::class);
        app()->singleton(UpdatesComments::class, UpdateComment::class);
        app()->singleton(DeletesComments::class, DeleteComment::class);
        app()->singleton(TogglesReactions::class, ToggleReaction::class);
        app()->singleton(UploadsImages::class, UploadImage::class);

        // Public waitlist endpoint: a few requests per minute and per IP address
        RateLimiter::for('waitlist', static function (Request $request) {
            return Limit::perMinute(Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE)->by($request->ip());
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

        // Each invitation sends an email (#1242)
        RateLimiter::for('invitations', static function (Request $request) {
            return Limit::perMinute(Constants::INVITATIONS_PER_MINUTE)->by(self::rateLimitKey($request));
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
