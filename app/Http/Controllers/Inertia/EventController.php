<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Notifications\TracksSpaceVisits;
use App\Enums\KnowiiNoAccessKind;
use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Support\CommunityRoles;
use App\Support\NoAccess;
use App\Support\SpaceTree;
use App\Support\TimeZones;
use App\Support\UpcomingEvents;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * The pages of an event of an events space (#1514, ADR 0053): the event, and the form of the host for a new event and
 * for a change. They only read: every change goes through the API (`EventApiController`).
 */
class EventController extends Controller
{
    /**
     * Show an event. A reader who may not see it (a draft that is not theirs) gets the answer of an event that does not
     * exist (404, ADR 0053 point 9), which is the sign-in page for a visitor (ADR 0044 point 4).
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $eventSlug): Response
    {
        [$community, $space] = self::space($request, $communitySlug, $spaceSlug);
        $event = self::event($community, $space, $eventSlug);
        $user = $request->user();

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Events/Show.tsx)
        return Jetstream::inertia()->render($request, 'Events/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $user)->toResourceCollection(),
            // The resource cuts the meeting address and the place for the readers who may not see them
            'event' => $event->toResource()->withLargeFields(),
            // All false for a visitor and for a person who is not a member (ADR 0044 point 8)
            'permissions' => [
                'canUpdate' => Gate::check('update', $event),
                'canCancel' => Gate::check('cancel', $event),
                'canDelete' => Gate::check('delete', $event),
                // A copy is a new event of the space: the right to change this one, and the right to create one
                'canDuplicate' => Gate::check('update', $event) && Gate::check('contribute', $space),
            ],
        ]);
    }

    /**
     * The form of a new event: the people who may create one in the space (its posting policy), else 403.
     */
    public function create(Request $request, string $communitySlug, string $spaceSlug): Response
    {
        [$community, $space] = self::space($request, $communitySlug, $spaceSlug);

        Gate::authorize('contribute', $space);

        return $this->form($request, $community, $space, null);
    }

    /**
     * The form of an event that exists: 404 for a reader who may not see it, 403 for a reader who may not change it.
     */
    public function edit(Request $request, string $communitySlug, string $spaceSlug, string $eventSlug): Response
    {
        [$community, $space] = self::space($request, $communitySlug, $spaceSlug);
        $event = self::event($community, $space, $eventSlug);

        Gate::authorize('update', $event);

        return $this->form($request, $community, $space, $event);
    }

    private function form(Request $request, Community $community, Space $space, ?Event $event): Response
    {
        $user = $request->user();

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Events/Form.tsx)
        return Jetstream::inertia()->render($request, 'Events/Form', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $user)->toResourceCollection(),
            // Null: a new event
            'event' => $event?->toResource()->withLargeFields(),
            // The zones that the server accepts: the form offers no other one
            'timeZones' => TimeZones::identifiers(),
            'permissions' => [
                // "Publish now" skips the 5 minutes before the members are told
                'canNotifyNow' => Gate::check('moderate', $space),
                // The owner and the admins see and change the events of every host
                'canManageAllEvents' => $user !== null && CommunityRoles::isOwnerOrAdmin($user, $community),
            ],
        ]);
    }

    /**
     * The community and its events space, for a reader who may read the space. A space of another type does not exist
     * here. A signed-in person who is not a member gets the page without access.
     *
     * @return array{0: Community, 1: Space}
     */
    private static function space(Request $request, string $communitySlug, string $spaceSlug): array
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->where('type', KnowiiSpaceType::Events)->firstOrFail();
        // The policies of the space and of its events read the community: it is known
        $space->setRelation('community', $community);

        NoAccess::authorizeView($request, $community, $space, KnowiiNoAccessKind::Space);

        // A member who opens the space has visited it: the dot of the navigation goes (#1476). Before the navigation is built
        if ($request->user() !== null) {
            app(TracksSpaceVisits::class)->visit($request->user(), $space);
        }

        return [$community, $space];
    }

    /**
     * The event of the space with this slug, when the reader may see it. A draft is for its host, the owner and the
     * admins: for the others it does not exist.
     */
    private static function event(Community $community, Space $space, string $eventSlug): Event
    {
        $event = $space->events()->where('slug', $eventSlug)->with(UpcomingEvents::RELATIONS)->firstOrFail();
        $event->setRelation('space', $space);
        $event->setRelation('community', $community);

        abort_unless(Gate::check('view', $event), 404);

        return $event;
    }
}
