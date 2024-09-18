<?php

use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\UserApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (){
  // Auth
  Route::post('auth/login', [LoginApiController::class, 'login']);

  // Utils
  Route::get('ping', [PingApiController::class, 'ping']);

  // Users
  // FIXME sensitive call that should use rate limiting
  Route::post('users/is-username-available', [UserApiController::class, 'isUsernameAvailable'])->middleware('auth:sanctum');

  // Communities
  Route::post('communities', [CommunityApiController::class, 'store'])->middleware('auth:sanctum');
});
