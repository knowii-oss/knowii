<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PingController;

Route::get('/v1/ping', [PingController::class, 'ping']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
