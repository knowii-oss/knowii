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
     * @return array<mixed>
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
        if ($request->user()) {
            // Add the user profile
            $userProfile = $request->user()->profile;
            $retVal['userProfile'] = $userProfile;
        }

        return $retVal;
    }
}
