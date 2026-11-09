<?php

use App\ApiRoutes;
use App\Http\Controllers\API\CommentApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityResourceCollectionApiController;
use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\MarkdownApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\PostApiController;
use App\Http\Controllers\API\ResourceTextArticleApiController;
use App\Http\Controllers\API\SpaceApiController;
use App\Http\Controllers\API\SpaceGroupApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\WaitlistApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get(ApiRoutes::USER, static function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix(ApiRoutes::VERSION)->group(function () {
    // Auth
    Route::post(ApiRoutes::AUTH_LOGIN, [LoginApiController::class, 'login']);

    // Utils
    Route::get(ApiRoutes::PING, [PingApiController::class, 'ping']);

    // Waitlist (public, rate limited: see AppServiceProvider)
    Route::post(ApiRoutes::WAITLIST, [WaitlistApiController::class, 'join'])->middleware('throttle:waitlist');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post(ApiRoutes::USERS_IS_USERNAME_AVAILABLE, [UserApiController::class, 'isUsernameAvailable']);

        Route::post(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'store']);
        Route::delete(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'destroy']);

        Route::post(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [CommunityResourceCollectionApiController::class, 'store']);
        Route::delete(ApiRoutes::COMMUNITY_RESOURCE_COLLECTION, [CommunityResourceCollectionApiController::class, 'destroy']);
        Route::post(ApiRoutes::COMMUNITY_RESOURCE_TEXT_ARTICLES, [ResourceTextArticleApiController::class, 'store']);

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

            // Comments (#1175)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'index']);
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'store'])->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'update']);
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'destroy']);
        });

        Route::post(ApiRoutes::MARKDOWN_PREVIEW, [MarkdownApiController::class, 'preview'])->middleware('throttle:markdown-preview');
    });
});
