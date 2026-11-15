<?php

namespace App\Http\Controllers\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * The notification settings of the member (#1497, design 5.9): "Your spaces", the level of each space of each community that
 * the member belongs to. The levels load from the API, one call per community, so the page needs the communities only.
 */
class NotificationSettingsController extends Controller
{
    final public function show(Request $request): Response
    {
        JsonResource::withoutWrapping();

        // WARNING: aligned with the props of Pages/Profile/Notifications.tsx
        return Jetstream::inertia()->render($request, 'Profile/Notifications', [
            'communities' => $request->user()?->allCommunities()->toResourceCollection() ?? [],
        ]);
    }
}
