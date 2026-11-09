<?php

use App\ApiRoutes;
use App\Http\Controllers\API\AccessGroupApiController;
use App\Http\Controllers\API\CommentApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityInvitationApiController;
use App\Http\Controllers\API\CommunityResourceApiController;
use App\Http\Controllers\API\CommunityResourceCommentApiController;
use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\MarkdownApiController;
use App\Http\Controllers\API\MemberApiController;
use App\Http\Controllers\API\ModeratorPermissionApiController;
use App\Http\Controllers\API\NewsletterApiController;
use App\Http\Controllers\API\PageApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\PostApiController;
use App\Http\Controllers\API\ReactionApiController;
use App\Http\Controllers\API\SpaceApiController;
use App\Http\Controllers\API\SpaceGroupApiController;
use App\Http\Controllers\API\UploadApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\WaitlistApiController;
use Illuminate\Support\Facades\Route;

Route::prefix(ApiRoutes::VERSION)->group(function () {
    // Auth
    // The limiter of the web login (email + IP): no unlimited guessing of passwords or codes (#1256)
    Route::post(ApiRoutes::AUTH_LOGIN, [LoginApiController::class, 'login'])->middleware('throttle:login');

    // Utils
    Route::get(ApiRoutes::PING, [PingApiController::class, 'ping']);

    // Waitlist (public, rate limited: see AppServiceProvider)
    Route::post(ApiRoutes::WAITLIST, [WaitlistApiController::class, 'join'])->middleware('throttle:waitlist');

    // Newsletter of the footer (public, rate limited, #938)
    Route::post(ApiRoutes::NEWSLETTER, [NewsletterApiController::class, 'subscribe'])->middleware('throttle:newsletter');

    // Every route names the token abilities that it needs (ADR 0022, #689). Session users pass every check
    Route::middleware('auth:sanctum')->group(function () {
        // The current user, as an API resource (#1145)
        Route::get(ApiRoutes::USER, [UserApiController::class, 'me'])->middleware('ability:user:read');
        Route::post(ApiRoutes::USER_AVATAR, [UserApiController::class, 'updateAvatar'])->middleware('ability:user:write')->middleware('throttle:uploads');
        Route::delete(ApiRoutes::USER_AVATAR, [UserApiController::class, 'deleteAvatar'])->middleware('ability:user:write');
        Route::post(ApiRoutes::USERS_IS_USERNAME_AVAILABLE, [UserApiController::class, 'isUsernameAvailable'])->middleware('ability:user:read');

        Route::post(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'store'])->middleware('ability:communities:write');
        Route::get(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'show'])->middleware('ability:communities:read');
        Route::patch(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'update'])->middleware('ability:communities:write');
        Route::delete(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'destroy'])->middleware('ability:communities:write');
        Route::patch(ApiRoutes::COMMUNITY_MODERATOR_PERMISSIONS, [ModeratorPermissionApiController::class, 'update'])->middleware('ability:communities:write');

        // Space groups and spaces (#1173). scopeBindings: a space group or space of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'index'])->middleware('ability:communities:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'store'])->middleware('ability:communities:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS_REORDER, [SpaceGroupApiController::class, 'reorder'])->middleware('ability:communities:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'update'])->middleware('ability:communities:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'destroy'])->middleware('ability:communities:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, [SpaceApiController::class, 'reorder'])->middleware('ability:communities:write');

            Route::post(ApiRoutes::COMMUNITY_SPACES, [SpaceApiController::class, 'store'])->middleware('ability:communities:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'show'])->middleware('ability:communities:read');
            Route::patch(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'update'])->middleware('ability:communities:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'destroy'])->middleware('ability:communities:write');

            // Posts (#1174)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'index'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:posts');
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'show'])->middleware('ability:posts:read');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'update'])->middleware('ability:posts:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'destroy'])->middleware('ability:posts:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_PIN, [PostApiController::class, 'pin'])->middleware('ability:posts:write');

            // Pages of page spaces (#1179)
            Route::get(ApiRoutes::COMMUNITY_SPACE_PAGES, [PageApiController::class, 'index'])->middleware('ability:pages:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_PAGES, [PageApiController::class, 'store'])->middleware('ability:pages:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_PAGES_ORDER, [PageApiController::class, 'reorder'])->middleware('ability:pages:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'show'])->middleware('ability:pages:read');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'update'])->middleware('ability:pages:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'destroy'])->middleware('ability:pages:write');

            // Resources of resources spaces (ADR 0015)
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'index'])->middleware('ability:resources:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'store'])->middleware('ability:resources:create,resources:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'show'])->middleware('ability:resources:read');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'update'])->middleware('ability:resources:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_CAPTURE, [CommunityResourceApiController::class, 'capture'])->middleware('ability:resources:write')->middleware('throttle:captures');

            // Comments on resources (#1034)
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'index'])->middleware('ability:resources:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'store'])->middleware('ability:resources:write')->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'update'])->middleware('ability:resources:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'destroy'])->middleware('ability:resources:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'listResourceComment'])->middleware('ability:resources:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleResourceComment'])->middleware('ability:resources:write')->middleware('throttle:reactions');

            // Comments (#1175)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'index'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'update'])->middleware('ability:posts:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'destroy'])->middleware('ability:posts:write');

            // Reactions (#1176)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'listPost'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'togglePost'])->middleware('ability:posts:write')->middleware('throttle:reactions');
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'listComment'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleComment'])->middleware('ability:posts:write')->middleware('throttle:reactions');
        });

        Route::post(ApiRoutes::COMMUNITY_UPLOADS, [UploadApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:uploads');

        // Members (#1177 search, #1242 management)
        Route::get(ApiRoutes::COMMUNITY_MEMBERS, [MemberApiController::class, 'index'])->middleware('ability:communities:read')->middleware('throttle:markdown-preview');
        Route::patch(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'update'])->middleware('ability:communities:write');
        Route::delete(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'destroy'])->middleware('ability:communities:write');

        // Access groups (#1241, ADR 0025). scopeBindings: a group of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [AccessGroupApiController::class, 'index'])->middleware('ability:groups:read');
            Route::post(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [AccessGroupApiController::class, 'store'])->middleware('ability:groups:write');
            Route::patch(ApiRoutes::COMMUNITY_ACCESS_GROUP, [AccessGroupApiController::class, 'update'])->middleware('ability:groups:write');
            Route::delete(ApiRoutes::COMMUNITY_ACCESS_GROUP, [AccessGroupApiController::class, 'destroy'])->middleware('ability:groups:write');
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS, [AccessGroupApiController::class, 'members'])->middleware('ability:groups:read');
            Route::post(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS, [AccessGroupApiController::class, 'addMember'])->middleware('ability:groups:write');
            Route::delete(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, [AccessGroupApiController::class, 'removeMember'])->middleware('ability:groups:write');
        });

        // Invitations (#1242). scopeBindings: an invitation of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'index'])->middleware('ability:communities:read');
            Route::post(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'store'])->middleware('ability:communities:write')->middleware('throttle:invitations');
            Route::delete(ApiRoutes::COMMUNITY_INVITATION, [CommunityInvitationApiController::class, 'destroy'])->middleware('ability:communities:write');
        });

        Route::post(ApiRoutes::MARKDOWN_PREVIEW, [MarkdownApiController::class, 'preview'])->middleware('ability:posts:read')->middleware('throttle:markdown-preview');
    });
});
