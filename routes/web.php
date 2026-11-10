<?php

use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityInvitationController;
use App\Http\Controllers\Inertia\CommunityResourceController;
use App\Http\Controllers\Inertia\ConversationController;
use App\Http\Controllers\Inertia\DashboardController;
use App\Http\Controllers\Inertia\MagicLinkController;
use App\Http\Controllers\Inertia\MemberController;
use App\Http\Controllers\Inertia\PageController;
use App\Http\Controllers\Inertia\PostController;
use App\Http\Controllers\Inertia\SpaceController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\RobotsController;
use App\Support\LegalPages;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Built from config/robots.php (#104): AI crawlers refused, member areas never crawled
Route::get('/robots.txt', RobotsController::class)->name('robots');

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
    Route::get('/community/{communitySlug}/about', [CommunityController::class, 'about'])->name('communities.about');
    Route::get('/community/{communitySlug}/settings', [CommunityController::class, 'settings'])->name('communities.settings');

    // The link of an invitation email (#1242): signed and temporary
    Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])->middleware('signed')->name('community-invitations.accept');

    Route::get('/community/{communitySlug}/member/{username}', [MemberController::class, 'show'])->name('members.show');
    // Direct messages (#1186)
    Route::get('/community/{communitySlug}/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/community/{communitySlug}/messages/{conversationCuid}', [ConversationController::class, 'show'])->name('messages.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}/post/{postSlug}', [PostController::class, 'show'])->name('posts.show');
    // Pages of page spaces (#1179)
    Route::get('/community/{communitySlug}/space/{spaceSlug}/page/{pageSlug}', [PageController::class, 'show'])->name('pages.show');

    // A resource of a resources space (ADR 0015). The page itself is #1133
    Route::get('/community/{communitySlug}/space/{spaceSlug}/resource/{resourceSlug}', [CommunityResourceController::class, 'show'])->name('resources.show');
});

// Sign in with a link sent by email (#703, ADR 0023)
Route::middleware('guest')->group(function () {
    Route::post('/login/link', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link')->name('login.link.store');
    Route::get('/login/link/{token}', [MagicLinkController::class, 'show'])->name('login.link.show');
    Route::post('/login/link/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:magic-link')->name('login.link.consume');
});

Route::inertia('/contact', 'Contact');
// The legal pages (#904): one controller, one Markdown file per page (App\Support\LegalPages)
foreach (array_keys(LegalPages::PAGES) as $legalPage) {
    Route::get('/'.$legalPage, [LegalPageController::class, 'show'])->defaults('page', $legalPage)->name($legalPage.'.show');
}
