<?php

use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityInvitationController;
use App\Http\Controllers\Inertia\CommunityResourceController;
use App\Http\Controllers\Inertia\ConversationController;
use App\Http\Controllers\Inertia\DashboardController;
use App\Http\Controllers\Inertia\DeleteAccountController;
use App\Http\Controllers\Inertia\MagicLinkController;
use App\Http\Controllers\Inertia\MemberController;
use App\Http\Controllers\Inertia\OpenIdConnectController;
use App\Http\Controllers\Inertia\PageController;
use App\Http\Controllers\Inertia\PostController;
use App\Http\Controllers\Inertia\SpaceController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\Webhooks\OidcEntitlementsWebhookController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\LegalPages;
use App\Support\OidcEntitlements;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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

    Route::get('/community/{communitySlug}/about', [CommunityController::class, 'about'])->name('communities.about');
    Route::get('/community/{communitySlug}/settings', [CommunityController::class, 'settings'])->name('communities.settings');

    // The link of an invitation email (#1242): signed and temporary
    Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])->middleware('signed')->name('community-invitations.accept');

    Route::get('/community/{communitySlug}/member/{username}', [MemberController::class, 'show'])->name('members.show');
    // Direct messages (#1186)
    Route::get('/community/{communitySlug}/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/community/{communitySlug}/messages/{conversationCuid}', [ConversationController::class, 'show'])->name('messages.show');
});

// The pages of the spaces that visitors may read (#1387, ADR 0044). Without an account the policies decide: a public
// space (not a chat space) is read, any other address sends the visitor to the sign-in page and back. A signed-in
// account goes through the same checks as the group above (session, verified email address)
Route::middleware([
    'auth.optional',
    config('jetstream.auth_session'),
])->group(function () {
    // The community home (#1485): a member gets the community, a visitor and an outsider get its public page if it has one
    Route::get('/community/{communitySlug}', [CommunityController::class, 'show'])->name('communities.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::get('/community/{communitySlug}/space/{spaceSlug}/post/{postSlug}', [PostController::class, 'show'])->name('posts.show');
    // Pages of page spaces (#1179)
    Route::get('/community/{communitySlug}/space/{spaceSlug}/page/{pageSlug}', [PageController::class, 'show'])->name('pages.show');

    // A resource of a resources space (ADR 0015). The page itself is #1133
    Route::get('/community/{communitySlug}/space/{spaceSlug}/resource/{resourceSlug}', [CommunityResourceController::class, 'show'])->name('resources.show');
});

// Delete the account (#1261, #1344): the same address and name as the Jetstream route, which cannot pass the option to
// delete the content of the member. The routes of the application are registered last, so this one answers
Route::middleware([
    'web',
    'auth:sanctum',
    config('jetstream.auth_session'),
])->delete('/user', [DeleteAccountController::class, 'destroy'])->name('current-user.destroy');

// Sign in with a link sent by email (#703, ADR 0023)
Route::middleware('guest')->group(function () {
    Route::post('/login/link', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link')->name('login.link.store');
    Route::get('/login/link/{token}', [MagicLinkController::class, 'show'])->name('login.link.show');
    Route::post('/login/link/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:magic-link')->name('login.link.consume');
});

// Sign in with an OpenID Connect provider, or connect it to the account of a signed-in member (#1385, ADR 0045). Both
// routes answer 404 unless the instance configures a provider. No guest middleware: a member connects an account here
Route::middleware('throttle:oidc')->prefix('auth/oidc')->group(function () {
    Route::get('/redirect', [OpenIdConnectController::class, 'redirect'])->name('oidc.redirect');
    Route::get('/callback', [OpenIdConnectController::class, 'callback'])->name('oidc.callback');
    // A signed-in member connects a provider account: a POST with the CSRF token, never a link (#1385)
    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
        ->post('/connect', [OpenIdConnectController::class, 'connect'])
        ->name('oidc.connect');
});

// The change call of the sign-in provider (#1386, ADR 0047). Public: no session, no cookie, no CSRF token and no token ability,
// because the proof is the signature of the call (a JWS checked against the keys of the issuer). Limited per IP address. The
// route answers 404 unless the instance has the entitlements feature on
Route::post(OidcEntitlements::WEBHOOK_PATH, [OidcEntitlementsWebhookController::class, 'store'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        HandleInertiaRequests::class,
    ])
    ->middleware('throttle:oidc-entitlements')
    ->name('oidc.entitlements.webhook');

Route::inertia('/contact', 'Contact');
// The legal pages (#904): one controller, one Markdown file per page (App\Support\LegalPages)
foreach (array_keys(LegalPages::PAGES) as $legalPage) {
    Route::get('/'.$legalPage, [LegalPageController::class, 'show'])->defaults('page', $legalPage)->name($legalPage.'.show');
}
