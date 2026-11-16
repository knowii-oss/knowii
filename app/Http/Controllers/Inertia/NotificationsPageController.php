<?php

namespace App\Http\Controllers\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * The page "Notifications" of the member (#1476, design 5.1 and 5.5): "Needs you", "Following" and "Earlier", with the "Catch up"
 * card of each community that has lines. The lists load from the API like the panel of the bell, so the page holds the
 * communities of the member only (the cards need their names and slugs).
 */
class NotificationsPageController extends Controller
{
    final public function index(Request $request): Response
    {
        JsonResource::withoutWrapping();

        // WARNING: aligned with the props of Pages/Notifications/Index.tsx
        return Jetstream::inertia()->render($request, 'Notifications/Index', [
            'communities' => $request->user()?->allCommunities()->toResourceCollection() ?? [],
        ]);
    }
}
