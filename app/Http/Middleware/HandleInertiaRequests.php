<?php

namespace App\Http\Middleware;

use App\Constants;
use App\Support\Analytics;
use App\Support\BrowserHistory;
use App\Support\InstanceValues;
use App\Support\LegalPages;
use App\Support\Newsletter;
use App\Support\OpenIdConnect;
use App\Support\SecretAddresses;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * A page asks the browser to encrypt its history only where the browser can do it (App\Support\BrowserHistory)
     */
    public function handle(Request $request, Closure $next)
    {
        BrowserHistory::decideEncryption($request);

        return parent::handle($request, $next);
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * An Inertia visit to an address that holds a secret never becomes the previous address of the session (ADR 0064),
     * also when `inertia.store_previous_url` is on
     */
    public function shouldStoreCurrentUrl(Request $request, SymfonyResponse $response): bool
    {
        return ! SecretAddresses::holdsASecret($request) && parent::shouldStoreCurrentUrl($request, $response);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $retVal = [...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            // The newsletter form of the footer (#938). Off at an address that holds a secret (ADR 0064): the form loads the
            // bot check of another host
            'newsletter' => fn () => SecretAddresses::holdsASecret($request) ? ['enabled' => false, 'turnstileSiteKey' => null] : Newsletter::pageProp(),
            // The values of this instance (#1653): the contact address, the publisher, the footer and the social links
            'instance' => fn () => InstanceValues::pageProp(),
            // The page view of the statistics (#1316, ADR 0064): null when the instance sets none and at an address that
            // holds a secret. Read when the response is built, so the error page of such an address gets null too
            'statistics' => fn () => Analytics::pageProp($request),
            // The legal pages that this instance has a text for (#1653): the footer, the sign-up and the forms name only
            // these, and a page that is not in the list answers 404
            'legalPages' => fn () => LegalPages::available(),
            // Sign in with an OpenID Connect provider (#1385): null when the instance has none, so that no page shows a
            // button. The error and the status come from the callback, for one request
            'oidc' => function () use ($request) {
                $error = $request->hasSession() ? $request->session()->get('oidc_error') : null;
                $status = $request->hasSession() ? $request->session()->get('oidc_status') : null;

                return OpenIdConnect::pageProp(is_string($error) ? $error : null, is_string($status) ? $status : null);
            },
        ];

        // The "Catch up" card (#1476): the date of the last visit when it was 7 days old or more, else null. Read when the
        // response is built, after the middleware that records the visit
        $retVal['catchUp'] = function () use ($request): ?array {
            if ($request->user() === null || ! $request->hasSession()) {
                return null;
            }
            $since = $request->session()->get(Constants::SESSION_CATCH_UP_SINCE);

            return is_string($since) ? ['since' => $since] : null;
        };

        // If the user is authenticated, load their communities
        $user = $request->user();
        if ($user !== null) {
            // Add the user profile
            $userProfile = $user->profile;
            $retVal['userProfile'] = $userProfile;
            // The bell shows the name of the community on each notification when the member is in more than one (#1484).
            // The closure keeps the user of this moment: a later middleware can change who the request answers for
            $retVal['communityCount'] = fn () => $user->communities()->count();

            // The account of the page is the one of Jetstream (its middleware shared it before this one), with the change of
            // the email address that waits (#1706, ADR 0058): both columns are hidden from every serialization, so they are
            // added here, for the member only
            $account = Inertia::getShared('auth.user');
            $retVal['auth'] = [
                'user' => static function () use ($account, $user): ?array {
                    $value = $account instanceof Closure ? $account() : $account;

                    return is_array($value) ? [...$value, ...$user->ownPendingEmailChange()] : null;
                },
            ];
        }

        return $retVal;
    }
}
