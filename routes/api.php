<?php

use App\ApiRoutes;
use App\Http\Controllers\API\CommentApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityInvitationApiController;
use App\Http\Controllers\API\CommunityResourceApiController;
use App\Http\Controllers\API\CommunityResourceCommentApiController;
use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\MarkdownApiController;
use App\Http\Controllers\API\MemberApiController;
use App\Http\Controllers\API\ModeratorPermissionApiController;
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
    Route::post(ApiRoutes::AUTH_LOGIN, [LoginApiController::class, 'login']);

    // Utils
    Route::get(ApiRoutes::PING, [PingApiController::class, 'ping']);

    // Waitlist (public, rate limited: see AppServiceProvider)
    Route::post(ApiRoutes::WAITLIST, [WaitlistApiController::class, 'join'])->middleware('throttle:waitlist');

    Route::middleware('auth:sanctum')->group(function () {
        // The current user, as an API resource (#1145)
        Route::get(ApiRoutes::USER, [UserApiController::class, 'me']);
        Route::post(ApiRoutes::USERS_IS_USERNAME_AVAILABLE, [UserApiController::class, 'isUsernameAvailable']);

        Route::post(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'store']);
        Route::get(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'show']);
        Route::delete(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'destroy']);
        Route::patch(ApiRoutes::COMMUNITY_MODERATOR_PERMISSIONS, [ModeratorPermissionApiController::class, 'update']);

        // Space groups and spaces (#1173). scopeBindings: a space group or space of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'store']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS_REORDER, [SpaceGroupApiController::class, 'reorder']);
            Route::patch(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'destroy']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, [SpaceApiController::class, 'reorder']);

            Route::post(ApiRoutes::COMMUNITY_SPACES, [SpaceApiController::class, 'store']);
            Route::get(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'show']);
            Route::patch(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'destroy']);

            // Posts (#1174)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'store'])->middleware('throttle:posts');
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'show']);
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'destroy']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_PIN, [PostApiController::class, 'pin']);

            // Resources of resources spaces (ADR 0015)
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'store']);
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'show']);

            // Comments on resources (#1034)
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'store'])->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'destroy']);
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'listResourceComment']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleResourceComment'])->middleware('throttle:reactions');

            // Comments (#1175)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'store'])->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'destroy']);

            // Reactions (#1176)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'listPost']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'togglePost'])->middleware('throttle:reactions');
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'listComment']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleComment'])->middleware('throttle:reactions');
        });

        Route::post(ApiRoutes::COMMUNITY_UPLOADS, [UploadApiController::class, 'store'])->middleware('throttle:uploads');

        // Members (#1177 search, #1242 management)
        Route::get(ApiRoutes::COMMUNITY_MEMBERS, [MemberApiController::class, 'index'])->middleware('throttle:markdown-preview');
        Route::patch(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'update']);
        Route::delete(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'destroy']);

        // Invitations (#1242). scopeBindings: an invitation of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'store'])->middleware('throttle:invitations');
            Route::delete(ApiRoutes::COMMUNITY_INVITATION, [CommunityInvitationApiController::class, 'destroy']);
        });

        Route::post(ApiRoutes::MARKDOWN_PREVIEW, [MarkdownApiController::class, 'preview'])->middleware('throttle:markdown-preview');
    });
});
