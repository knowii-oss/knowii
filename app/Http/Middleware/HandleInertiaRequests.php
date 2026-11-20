<?php

namespace App\Http\Middleware;

use App\Constants;
use App\Support\InstanceValues;
use App\Support\LegalPages;
use App\Support\Newsletter;
use App\Support\OpenIdConnect;
use Illuminate\Http\Request;
use Inertia\Middleware;
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
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
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
            // The newsletter form of the footer (#938)
            'newsletter' => fn () => Newsletter::pageProp(),
            // The values of this instance (#1653): the contact address, the publisher, the footer and the social links
            'instance' => fn () => InstanceValues::pageProp(),
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
        }

        return $retVal;
    }
}
