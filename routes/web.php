<?php

use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityResourceCollectionController;
use App\Http\Controllers\Inertia\CommunityResourceController;
use App\Http\Controllers\Inertia\DashboardController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TermsOfServiceController;
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
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('/community/{communitySlug}', [CommunityController::class, 'show'])->name('communities.show');
    Route::get('/community/{communitySlug}/settings', [CommunityController::class, 'settings'])->name('communities.settings');

    Route::get('/community/{communitySlug}/resource-collection/{resourceCollectionSlug}', [CommunityResourceCollectionController::class, 'show'])->name('resource-collections.show');

    // FIXME implement
    Route::get('/community/{communitySlug}/resource-collection/{resourceCollectionSlug}/{resourceSlug}', [CommunityResourceController::class, 'show'])->name('resources.show');
});

Route::inertia('/contact', 'Contact');
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show'])->name('privacy-policy.show');
Route::get('/terms-of-service', [TermsOfServiceController::class, 'show'])->name('terms-of-service.show');
