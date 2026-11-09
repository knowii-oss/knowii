<?php

use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityInvitationController;
use App\Http\Controllers\Inertia\CommunityResourceController;
use App\Http\Controllers\Inertia\DashboardController;
use App\Http\Controllers\Inertia\MemberController;
use App\Http\Controllers\Inertia\PostController;
use App\Http\Controllers\Inertia\SpaceController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TermsOfServiceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Render the homepage
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'waitlist' => [
            // The form is only shown when the EmailOctopus list and the Turnstile bot check are configured
            'enabled' => filled(config('services.emailoctopus.api_key'))
                && filled(config('services.emailoctopus.waitlist_list_id'))
                && filled(config('services.turnstile.site_key'))
                && filled(config('services.turnstile.secret_key')),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ],
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

    // The link of an invitation email (#1242): signed and temporary
    Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])->middleware('signed')->name('community-invitations.accept');

    Route::get('/community/{communitySlug}/member/{username}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}/post/{postSlug}', [PostController::class, 'show'])->name('posts.show');

    // A resource of a resources space (ADR 0015). The page itself is #1133
    Route::get('/community/{communitySlug}/space/{spaceSlug}/resource/{resourceSlug}', [CommunityResourceController::class, 'show'])->name('resources.show');
});

Route::inertia('/contact', 'Contact');
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show'])->name('privacy-policy.show');
Route::get('/terms-of-service', [TermsOfServiceController::class, 'show'])->name('terms-of-service.show');
