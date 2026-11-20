<?php

namespace App\Http\Controllers\API;

use App\Actions\Events\EventPublication;
use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\DeletesEvents;
use App\Contracts\Events\DuplicatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Support\EventAnswers;
use App\Support\Pagination;
use App\Support\PublicCommunity;
use App\Support\UpcomingEvents;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The events of the events spaces (#1513, ADR 0053). Changes are delegated to the actions (ADR 0006). The start and the
 * end are UTC instants; a request gives them in ISO 8601 with the offset of the time zone of the event. The meeting
 * address and the place are cut on the server for the readers who may not see them. An event carries the answer of the
 * reader (`my_answer`) and, with a limit of places, the places that are left (`places_left`): never the answer of another
 * member, and the limit itself (`capacity`) only for the host, the owner and the admins, and with a token only with the
 * ability `event-answers:read` (#1515, `EventAnswerApiController`).
 */
class EventApiController extends Controller
{
    use ApiResponses;

    private const array RELATIONS = UpcomingEvents::RELATIONS;

    private const string SCOPE_UPCOMING = 'upcoming';

    private const string SCOPE_PAST = 'past';

    /**
     * List the events of an events space. `upcoming` (the default): the events that end now or later, the drafts first,
     * then the next start first. `past`: the events that ended, the newest start first. A cancelled event stays in the
     * list with its state. A draft is listed only for its host, the owner and the admins. Offset pagination (ADR 0011).
     * Lists never carry the description: get one event for it.
     */
    #[QueryParameter('scope', description: 'upcoming (default) or past', type: 'string', example: 'upcoming')]
    #[QueryParameter('limit', description: 'Number of events (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of events to skip (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);
        self::assertEventsSpace($space);

        $scope = $request->query('scope', self::SCOPE_UPCOMING);
        if ($scope !== self::SCOPE_UPCOMING && $scope !== self::SCOPE_PAST) {
            throw ValidationException::withMessages(['scope' => 'Use upcoming or past']);
        }

        $query = $space->events()->visibleTo($request->user(), $community)->with(self::RELATIONS);
        $page = Pagination::fromRequest($request)->apply($scope === self::SCOPE_PAST ? $query->past() : $query->upcoming());
        self::inSpace($page['items'], $space);

        // The answers of the reader and the places load once for the page (#1515)
        return EventAnswers::forList($page['items'], $request->user(), $community, static fn (): JsonResponse => self::success('Events', $page['metadata'], $page['items']->toResourceCollection()));
    }

    /**
     * The upcoming events of every events space that the reader can read, the next start first: never a draft. A member
     * gets the events spaces of their navigation. A visitor and a signed-in person who is not a member get the public
     * events spaces, when the community has a public page: 404 to a visitor and 403 to the signed-in person otherwise.
     * Offset pagination (ADR 0011).
     */
    #[QueryParameter('limit', description: 'Number of events (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of events to skip (default 0)', type: 'int', example: 0)]
    final public function community(Request $request, Community $community): JsonResponse
    {
        $user = $request->user();
        if (! PublicCommunity::isMember($user, $community)) {
            PublicCommunity::ensureExists($community, $request);
        }

        // The same query as "Next events" on the home and on the public page of the community (#1514)
        $spaces = UpcomingEvents::spaces($community, $user);
        $page = Pagination::fromRequest($request)->apply(UpcomingEvents::query($community, $spaces));
        UpcomingEvents::inSpaces($page['items'], $community, $spaces);

        return EventAnswers::forList($page['items'], $user, $community, static fn (): JsonResponse => self::success('Upcoming events', $page['metadata'], $page['items']->toResourceCollection()));
    }

    /**
     * Get an event with its description (Markdown and HTML). A draft is for its host, the owner and the admins. A
     * signed-in reader who cannot see the event (a draft of another host, or an event of a space that the reader cannot
     * read) gets 404, with the body of an event that does not exist: the same answer as every other route of an event
     * (ADR 0053, "The answers and the places", point 3).
     */
    final public function show(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            EventPublication::assertVisible($user, $event);
        }
        // A visitor: the answer of the public reads (ADR 0044)
        Gate::authorize('view', $event);

        return self::success('Event', null, self::loaded($event)->toResource()->withLargeFields());
    }

    /**
     * Create an event in an events space. The posting policy of the space decides who can create one. A draft by default:
     * only its host, the owner and the admins see it. In a public space (`visibility` public), anyone on the internet can
     * read a published event without an account: its title, cover, description, times, zone, state and host (name,
     * username and picture), never its meeting address or its place.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title (3 to 200 characters). In a public space, anyone on the internet can read it', required: true, type: 'string', example: 'Weekly review office hour')]
    #[BodyParameter('body', description: 'Description in Markdown (up to 20 000 characters). Raw HTML is escaped. Default empty. In a public space, anyone on the internet can read it', required: false, type: 'string', example: 'Bring your questions about the weekly review.')]
    #[BodyParameter('starts_at', description: 'Start in ISO 8601 with the offset of the time zone at that time. A value without an offset, a wrong offset or a time that does not exist in the zone is refused (400). A start in the past is for the owner and the admins only', required: true, type: 'string', format: 'date-time', example: '2026-11-18T17:00:00+01:00')]
    #[BodyParameter('ends_at', description: 'End in ISO 8601 with the offset of the time zone: after the start, at most 24 hours later', required: true, type: 'string', format: 'date-time', example: '2026-11-18T18:00:00+01:00')]
    #[BodyParameter('time_zone', description: 'The IANA time zone in which the times are entered. It never changes the moment. An older name of a zone is stored under the current name', required: true, type: 'string', example: 'Europe/Brussels')]
    #[BodyParameter('meeting_url', description: 'Meeting address: an absolute http or https URL (up to 2048 characters). Knowii never opens it. Never shown to a visitor or to a person who is not a member', required: false, type: 'string', format: 'uri', example: 'https://meet.example.com/abc-defg-hij')]
    #[BodyParameter('place', description: 'Place (up to 255 characters). Never shown to a visitor or to a person who is not a member', required: false, type: 'string', example: 'Room 2, Main street 1, Brussels')]
    #[BodyParameter('location_visibility', description: 'Who gets the meeting address and the place: members (default, every member who can see the event) or going (the members who answer Yes, the host, the owner and the admins)', required: false, type: 'string', example: 'members')]
    #[BodyParameter('capacity', description: 'The limit of places: 1 to 5 000, or null for no limit (the default). It counts the Yes answers only: a new Yes on a full event is refused. Members read the places that are left, never the limit', required: false, type: 'int', example: 40)]
    #[BodyParameter('replay_expected', description: 'true: a replay will exist, and members may answer "Replay only". Default false', required: false, type: 'bool', example: false)]
    #[BodyParameter('cover_upload_cuid', description: 'The cuid of an image uploaded in this community (POST communities/{community}/uploads). In a public space, anyone on the internet can see it', required: false, type: 'string')]
    #[BodyParameter('cover_alt', description: 'Text alternative of the cover (up to 125 characters). Empty: a decorative image', required: false, type: 'string')]
    #[BodyParameter('host_cuid', description: 'The user cuid of the host: a member of the community who can read the space. Default: you. In a public space, anyone on the internet can read the name, the username and the picture of the host', required: false, type: 'string')]
    #[BodyParameter('state', description: 'draft (default) or published. A published event with a start in the future tells the members at Everything after 5 minutes', required: false, type: 'string', example: 'draft')]
    #[BodyParameter('notify_now', description: 'With state published: true tells the members at Everything now, without the 5 minutes of waiting. For the people who can moderate the space only (403 for the others). Default false', required: false, type: 'bool', example: false)]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $event = app(CreatesEvents::class)->create($request->user() ?? throw new AuthenticationException, $space, $request->only([
            'title', 'body', 'starts_at', 'ends_at', 'time_zone', 'meeting_url', 'place', 'location_visibility', 'capacity', 'replay_expected', 'cover_upload_cuid', 'cover_alt', 'host_cuid', 'state', 'notify_now',
        ]));

        return self::created(self::loaded($event)->toResource()->withLargeFields(), 'Event created');
    }

    /**
     * Change an event: the owner, the admins and the host. Each field is optional; the times are checked with the stored
     * values of the fields that are absent. The slug stays. The state has its own route. A change tells nobody.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title (3 to 200 characters)', required: false, type: 'string')]
    #[BodyParameter('body', description: 'Description in Markdown (up to 20 000 characters)', required: false, type: 'string')]
    #[BodyParameter('starts_at', description: 'Start in ISO 8601 with the offset of the time zone at that time. A new start in the past is for the owner and the admins only', required: false, type: 'string', format: 'date-time', example: '2026-11-18T17:00:00+01:00')]
    #[BodyParameter('ends_at', description: 'End in ISO 8601 with the offset of the time zone: after the start, at most 24 hours later', required: false, type: 'string', format: 'date-time', example: '2026-11-18T18:00:00+01:00')]
    #[BodyParameter('time_zone', description: 'The IANA time zone in which the times are entered', required: false, type: 'string', example: 'Europe/Brussels')]
    #[BodyParameter('meeting_url', description: 'Meeting address (http or https); empty removes it', required: false, type: 'string', format: 'uri')]
    #[BodyParameter('place', description: 'Place (up to 255 characters); empty removes it', required: false, type: 'string')]
    #[BodyParameter('location_visibility', description: 'members (every member who can see the event) or going (the members who answer Yes, the host, the owner and the admins)', required: false, type: 'string')]
    #[BodyParameter('capacity', description: 'The limit of places: 1 to 5 000; null removes the limit. A value under the number of Yes answers is accepted: nobody is removed, and no new Yes passes', required: false, type: 'int', example: 40)]
    #[BodyParameter('replay_expected', description: 'true: a replay will exist, and members may answer "Replay only". false keeps the "Replay only" answers that exist', required: false, type: 'bool')]
    #[BodyParameter('cover_upload_cuid', description: 'The cuid of an image uploaded in this community; null removes the cover', required: false, type: 'string')]
    #[BodyParameter('cover_alt', description: 'Text alternative of the cover (up to 125 characters)', required: false, type: 'string')]
    #[BodyParameter('host_cuid', description: 'The user cuid of the new host: a member of the community who can read the space', required: false, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $event = app(UpdatesEvents::class)->update($request->user() ?? throw new AuthenticationException, $event, $request->only([
            'title', 'body', 'starts_at', 'ends_at', 'time_zone', 'meeting_url', 'place', 'location_visibility', 'capacity', 'replay_expected', 'cover_upload_cuid', 'cover_alt', 'host_cuid',
        ]));

        return self::success('Event updated', null, self::loaded($event)->toResource()->withLargeFields());
    }

    /**
     * Change the state of an event: draft to published, published to draft (not once it started, and not once a member
     * answered it: cancel it), published to cancelled, cancelled to published (the answers of the members are still
     * there). Any other change is refused (422). The same state again changes nothing. A cancel is for the owner, the
     * admins, the host and the moderators of the space; the other changes for the owner, the admins and the host.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('state', description: 'draft, published or cancelled', required: true, type: 'string', example: 'published')]
    #[BodyParameter('notify_now', description: 'With state published: true tells the members at Everything now, without the 5 minutes of waiting. For the people who can moderate the space only (403 for the others). Default false', required: false, type: 'bool', example: false)]
    final public function state(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $event = app(SetsEventStates::class)->setState($request->user() ?? throw new AuthenticationException, $event, $request->only(['state', 'notify_now']));

        return self::success('Event state', null, self::loaded($event)->toResource()->withLargeFields());
    }

    /**
     * A draft copy of the event in the same space, 7 days later at the same wall-clock time in the zone of the event: the
     * owner, the admins and the host.
     *
     * @throws AuthenticationException
     */
    final public function duplicate(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $copy = app(DuplicatesEvents::class)->duplicate($request->user() ?? throw new AuthenticationException, $event);

        return self::created(self::loaded($copy)->toResource()->withLargeFields(), 'Event duplicated');
    }

    /**
     * Delete an event: the owner, the admins, the host and the moderators of the space.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Event $event): Response
    {
        app(DeletesEvents::class)->delete($request->user() ?? throw new AuthenticationException, $event);

        return self::deleted();
    }

    private static function loaded(Event $event): Event
    {
        return $event->load([...self::RELATIONS, 'space']);
    }

    /**
     * The policy of each event reads its space and its community: they are known, so no query per event
     *
     * @param  Collection<int, Event>  $events
     */
    private static function inSpace(Collection $events, Space $space): void
    {
        foreach ($events as $event) {
            $event->setRelation('space', $space);
            $event->setRelation('community', $space->community);
        }
    }

    private static function assertEventsSpace(Space $space): void
    {
        if ($space->type !== KnowiiSpaceType::Events) {
            throw new BusinessException('Events only exist in events spaces');
        }
    }
}
