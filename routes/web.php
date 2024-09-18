<?php

use App\Http\Controllers\CommunityInvitationController;
use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityMemberController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Render the homepage
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

  Route::get('/community/{communitySlug}', [CommunityController::class, 'show'])->name('communities.show');
});

Route::inertia('/contact', 'Contact');
