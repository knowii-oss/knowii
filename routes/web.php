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

  // FIXME replace parameter by the community slug and adapt link in Dashboard.tsx
  Route::get('/community/{communitySlug}', [CommunityController::class, 'show'])->name('communities.show');
});

Route::inertia('/contact', 'Contact');

// FIXME implement
Route::post('/communities/{community}/members', [CommunityMemberController::class, 'store'])->name('community-members.store');
Route::put('/communities/{community}/members/{user}', [CommunityMemberController::class, 'update'])->name('community-members.update');
Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy'])->name('community-members.destroy');

Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])
  ->middleware(['signed'])
  ->name('community-invitations.accept');

Route::delete('/community-invitations/{invitation}', [CommunityInvitationController::class, 'destroy'])
  ->name('community-invitations.destroy');
