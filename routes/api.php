<?php

use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\ResourceTextArticleApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityResourceCollectionApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', static function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (){
  // Auth
  Route::post('auth/login', [LoginApiController::class, 'login']);

  // Utils
  Route::get('ping', [PingApiController::class, 'ping']);

  Route::middleware('auth:sanctum')->group(function () {
    Route::post('users/is-username-available', [UserApiController::class, 'isUsernameAvailable']);

    Route::post('communities', [CommunityApiController::class, 'store']);
    Route::post('communities/{community:cuid}/resource-collections', [CommunityResourceCollectionApiController::class, 'store']);
    Route::post('communities/{community:cuid}/resource-collections/{community_resource_collection:cuid}/text-articles', [ResourceTextArticleApiController::class, 'store']);
  });
});
