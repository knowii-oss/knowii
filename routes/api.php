<?php

use App\ApiRoutes;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityResourceCollectionApiController;
use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\ResourceTextArticleApiController;
use App\Http\Controllers\API\UserApiController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post(ApiRoutes::USERS_IS_USERNAME_AVAILABLE, [UserApiController::class, 'isUsernameAvailable']);

        Route::post(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'store']);
        Route::delete(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'destroy']);

        Route::post(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [CommunityResourceCollectionApiController::class, 'store']);
        Route::delete(ApiRoutes::COMMUNITY_RESOURCE_COLLECTION, [CommunityResourceCollectionApiController::class, 'destroy']);
        Route::post(ApiRoutes::COMMUNITY_RESOURCE_TEXT_ARTICLES, [ResourceTextArticleApiController::class, 'store']);
    });
});
