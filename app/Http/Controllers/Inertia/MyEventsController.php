<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Support\MyEvents;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * The page "My events" (#1515, design 6.8, ADR 0053 "The calendar file and My events"): the events that the member
 * answered Yes or Replay only and the events that they host, in all their communities. The first page of the open list
 * comes with the page; more load from `GET user/events`. The page opens no live connection: it shows what the server gave
 * when it loaded.
 */
class MyEventsController extends Controller
{
    final public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Any value of `scope` other than "past" means "upcoming", as on the page of an events space
        $scope = $request->query('scope') === MyEvents::SCOPE_PAST ? MyEvents::SCOPE_PAST : MyEvents::SCOPE_UPCOMING;
        $page = (new Pagination(SpaceController::EVENTS_PER_PAGE, 0))->apply(MyEvents::query($user, $scope));

        // The name of the community on each row, for a member of more than one community. The personal community of the
        // account is its own place, not a community that it shares: it counts only when "My events" holds one of its events
        // (it may hold an events space), because the rows then come from two communities
        $communities = $user->allCommunities();
        $shared = $communities->filter(static fn (Community $community): bool => $community->visibility !== KnowiiCommunityVisibility::Personal)->count();
        $personal = $communities->first(static fn (Community $community): bool => $community->visibility === KnowiiCommunityVisibility::Personal);
        $showCommunity = $shared > 1
            || ($shared === 1 && $personal instanceof Community && MyEvents::events($user)->where('events.community_id', $personal->id)->exists());

        JsonResource::withoutWrapping();

        // WARNING: aligned with the props of Pages/Events/Index.tsx
        return Jetstream::inertia()->render($request, 'Events/Index', [
            'events' => [
                'items' => MyEvents::forList($page['items'], $user, static fn (): array => $page['items']->toResourceCollection()->resolve($request)),
                'pagination' => $page['metadata']['pagination'],
            ],
            'scope' => $scope,
            'showCommunity' => $showCommunity,
        ]);
    }
}
