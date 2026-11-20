<?php

use App\Http\Controllers\Inertia\CommunityController;
use App\Http\Controllers\Inertia\CommunityInvitationController;
use App\Http\Controllers\Inertia\CommunityResourceController;
use App\Http\Controllers\Inertia\ConversationController;
use App\Http\Controllers\Inertia\DashboardController;
use App\Http\Controllers\Inertia\DeleteAccountController;
use App\Http\Controllers\Inertia\EventController;
use App\Http\Controllers\Inertia\MagicLinkController;
use App\Http\Controllers\Inertia\MemberController;
use App\Http\Controllers\Inertia\NotificationSettingsController;
use App\Http\Controllers\Inertia\NotificationsPageController;
use App\Http\Controllers\Inertia\OpenIdConnectController;
use App\Http\Controllers\Inertia\PageController;
use App\Http\Controllers\Inertia\PostController;
use App\Http\Controllers\Inertia\SpaceController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SecurityTxtController;
use App\Http\Controllers\Webhooks\OidcEntitlementsWebhookController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\InstanceValues;
use App\Support\LegalPages;
use App\Support\OidcEntitlements;
use App\Support\SecurityTxt;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

// Built from config/robots.php (#104): AI crawlers refused, member areas never crawled
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Built from config/security_txt.php (#1559, RFC 9116): the security contact of this instance
Route::get(SecurityTxt::PATH, [SecurityTxtController::class, 'show'])->name('security-txt');
Route::get('/security.txt', [SecurityTxtController::class, 'legacy']);

Route::get('/', function () {
    // The home page (#1574): what a person can do, and the way in. Registration is open while the Fortify feature is on
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

    // The page "Notifications": Needs you, Following, Earlier, and the "Catch up" cards (#1476)
    Route::get('/notifications', [NotificationsPageController::class, 'index'])->name('notifications.index');

    // The notification settings of the member: the level of each space (#1497)
    Route::get('/user/notifications', [NotificationSettingsController::class, 'show'])->name('profile.notifications');

    Route::get('/community/{communitySlug}/about', [CommunityController::class, 'about'])->name('communities.about');
    Route::get('/community/{communitySlug}/settings', [CommunityController::class, 'settings'])->name('communities.settings');

    // The link of an invitation email (#1242): signed and temporary
    Route::get('/community-invitations/{invitation}', [CommunityInvitationController::class, 'accept'])->middleware('signed')->name('community-invitations.accept');

    Route::get('/community/{communitySlug}/member/{username}', [MemberController::class, 'show'])->name('members.show');
    // Direct messages (#1186)
    Route::get('/community/{communitySlug}/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/community/{communitySlug}/messages/{conversationCuid}', [ConversationController::class, 'show'])->name('messages.show');

    // The form of the host of an event (#1514, ADR 0053). "events/new" is plural, so an event with the slug "new" never
    // collides with it
    Route::get('/community/{communitySlug}/space/{spaceSlug}/events/new', [EventController::class, 'create'])->name('events.create');
    Route::get('/community/{communitySlug}/space/{spaceSlug}/event/{eventSlug}/edit', [EventController::class, 'edit'])->name('events.edit');
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

    // An event of an events space (#1514, ADR 0053): a visitor reads it in a public space, without its meeting address
    Route::get('/community/{communitySlug}/space/{spaceSlug}/event/{eventSlug}', [EventController::class, 'show'])->name('events.show');
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

// Sign in with an OpenID Connect provider, or connect it to the account of a signed-in member (#1385, ADR 0045), or confirm
// the deletion of an account without a password (#1521, ADR 0052). The routes answer 404 unless the instance configures a
// provider. No guest middleware: a member connects an account here
Route::middleware('throttle:oidc')->prefix('auth/oidc')->group(function () {
    Route::get('/redirect', [OpenIdConnectController::class, 'redirect'])->name('oidc.redirect');
    Route::get('/callback', [OpenIdConnectController::class, 'callback'])->name('oidc.callback');
    // A signed-in member connects a provider account: a POST with the CSRF token, never a link (#1385)
    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
        ->post('/connect', [OpenIdConnectController::class, 'connect'])
        ->name('oidc.connect');
    // A signed-in member without a password proves with a new sign-in that they want to delete the account (#1521, ADR 0052):
    // a POST with the CSRF token, never a link. The callback above records the proof; it never deletes anything
    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
        ->post('/confirm-deletion', [OpenIdConnectController::class, 'confirmDeletion'])
        ->name('oidc.confirm-deletion');
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

// The contact page shows the contact address of the instance (#1653): without an address, the page does not exist
Route::get('/contact', static function (): InertiaResponse {
    abort_if(InstanceValues::contactEmail() === '', 404);

    return Inertia::render('Contact');
});
// The legal pages (#904): one controller, one Markdown file per page (App\Support\LegalPages). Every route stays registered,
// for the route cache and for the route names of the front end: a page without a file answers 404 in the controller (#1653)
foreach (array_keys(LegalPages::PAGES) as $legalPage) {
    Route::get('/'.$legalPage, [LegalPageController::class, 'show'])->defaults('page', $legalPage)->name($legalPage.'.show');
}

// An address that matches no route (#1574): the answer is still a 404, but it goes through the web middleware, so that the
// error page knows who is signed in and shows the header of a member. Every method answers 404 (a GET-only fallback would
// make the other methods answer 405), without the CSRF check, because nothing changes here. The API keeps its JSON 404
Route::any('{fallbackPlaceholder}', static fn () => abort(404))
    ->where('fallbackPlaceholder', '(?!api(?:/|$)).*')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->fallback();
