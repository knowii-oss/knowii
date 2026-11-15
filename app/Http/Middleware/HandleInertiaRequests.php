<?php

namespace App\Http\Middleware;

use App\Support\Newsletter;
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
        ];

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
