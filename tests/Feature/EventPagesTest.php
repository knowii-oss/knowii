<?php

use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Http\Controllers\Inertia\SpaceController;
use App\Models\AccessGroup;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/*
 * The screens of events (#1514, ADR 0053, slice E1b): the list of an events space, the event page, the form of the host
 * and "Next events" on the home and on the public page of a community. Seven readers (the owner, an admin, a moderator,
 * the host, a member, an outsider and a visitor) against a public, a members and a groups space. The controllers only
 * read; the meeting address and the place never reach a reader who may not see them.
 */

const EVP_MEETING_URL = 'https://meet.example.com/the-secret-room-of-the-pages';

const EVP_PLACE = 'Cellar of the old town hall';

const EVP_READERS = ['owner', 'admin', 'moderator', 'host', 'member', 'outsider', 'visitor'];

// The most queries that a page with events may run, whatever the number of its events: the page of a space (36 for a
// member on 2026-11-20) and the home of a community (45). A query per event of a page of 20 would pass both
const EVP_MAX_QUERIES = ['events' => 40, 'nextEvents' => 48];

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    // The host is a plain member: the rights of a host never come from a role
    $this->c['host'] = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($this->c['host'], ['role' => KnowiiCommunityMemberRole::Member]);

    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $make = fn (KnowiiSpaceVisibility $visibility, KnowiiSpacePostingPolicy $policy) => Space::factory()->inGroup($this->group)
        ->create(['type' => KnowiiSpaceType::Events, 'visibility' => $visibility, 'posting_policy' => $policy]);
    $this->spaces = [
        'public' => $make(KnowiiSpaceVisibility::Public, KnowiiSpacePostingPolicy::Moderators),
        'members' => $make(KnowiiSpaceVisibility::Members, KnowiiSpacePostingPolicy::Members),
        'groups' => $make(KnowiiSpaceVisibility::Groups, KnowiiSpacePostingPolicy::Members),
    ];
    // The host reads the groups space through an access group; the moderator and the member do not
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $this->spaces['groups']->accessGroups()->attach($buyers);
    $buyers->members()->attach($this->c['host']);

    $this->published = [];
    $this->drafts = [];
    foreach ($this->spaces as $visibility => $space) {
        $this->published[$visibility] = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])
            ->create(['title' => "Published {$visibility}", 'meeting_url' => EVP_MEETING_URL, 'place' => EVP_PLACE]);
        $this->drafts[$visibility] = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->draft()
            ->create(['title' => "Draft {$visibility}", 'meeting_url' => EVP_MEETING_URL, 'place' => EVP_PLACE]);
    }
});

function evpSpaceUrl(Space $space, string $query = ''): string
{
    return route('spaces.show', ['communitySlug' => test()->c['community']->slug, 'spaceSlug' => $space->slug]).$query;
}

function evpEventUrl(EventModel $event, string $route = 'events.show'): string
{
    return route($route, ['communitySlug' => test()->c['community']->slug, 'spaceSlug' => $event->space->slug, 'eventSlug' => $event->slug]);
}

function evpCreateUrl(Space $space): string
{
    return route('events.create', ['communitySlug' => test()->c['community']->slug, 'spaceSlug' => $space->slug]);
}

function evpHomeUrl(): string
{
    return route('communities.show', ['communitySlug' => test()->c['community']->slug]);
}

function evpGet(string $reader, string $url): TestResponse
{
    $test = test();
    if ($reader === 'visitor') {
        // Nobody is signed in, also after an earlier request of the test as someone else
        app('auth')->forgetGuards();

        return $test->get($url);
    }

    return $test->actingAs($test->c[$reader])->get($url);
}

/**
 * @return array<string, mixed>
 */
function evpProps(TestResponse $response): array
{
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

/**
 * The answer of a page for a reader: 200, 403, 404, or "login" (the sign-in page, for a visitor)
 */
function evpAssertAnswer(string $reader, string $url, int|string $expected): void
{
    $response = evpGet($reader, $url);
    if ($expected === 'login') {
        $response->assertRedirect(route('login'));

        return;
    }

    expect($response->getStatusCode())->toBe($expected, "{$reader} on {$url}");
}

/**
 * @param  array<mixed>  $props
 * @return list<string>
 */
function evpTitles(array $props, string $key = 'events'): array
{
    $list = $props[$key] ?? [];
    $items = is_array($list) && array_key_exists('items', $list) ? $list['items'] : $list;

    return array_column((array) json_decode((string) json_encode($items), true), 'title');
}

// ---------------------------------------------------------------------------------------------------------------------
// The list of an events space
// ---------------------------------------------------------------------------------------------------------------------

test('the page of an events space is read by the same readers as the space', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpSpaceUrl($this->spaces[$visibility]), $expected[$reader]);
    }
})->with([
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 200, 'member' => 200, 'outsider' => 200, 'visitor' => 200]],
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 200, 'member' => 200, 'outsider' => 403, 'visitor' => 'login']],
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('the page of an events space gives the first 20 upcoming events and the scope; another type of space gets no events', function () {
    $space = $this->spaces['members'];
    EventModel::factory()->count(21)->inSpace($space)->hostedBy($this->c['host'])->create();

    $props = evpProps(evpGet('member', evpSpaceUrl($space))->assertOk());

    expect($props['eventsScope'])->toBe('upcoming')
        ->and(evpTitles($props))->toHaveCount(SpaceController::EVENTS_PER_PAGE)
        ->and(SpaceController::EVENTS_PER_PAGE)->toBe(20)
        ->and($props['events']['pagination']['totalCount'])->toBe(22)
        ->and($props['events']['pagination']['nextOffset'])->toBe(20)
        ->and($props['events']['pagination']['limit'])->toBe(20);

    $first = (array) json_decode((string) json_encode($props['events']['items']), true)[0];
    // A list carries no description, and each event names its space and its host
    expect($first['body'])->toBeNull()->and($first['body_html'])->toBeNull()
        ->and($first['space']['slug'])->toBe($space->slug)
        ->and($first['host']['cuid'])->toBe($this->c['host']->cuid);

    $discussion = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);
    $other = evpProps(evpGet('member', evpSpaceUrl($discussion))->assertOk());
    expect($other['events'])->toBeNull()->and($other['eventsScope'])->toBeNull();
});

test('the scope "past" gives the events that ended, the newest start first; any other value means "upcoming"', function () {
    $space = $this->spaces['members'];
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->past()->create(['title' => 'Last week']);
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['title' => 'Two weeks ago', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addHour()]);
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->cancelled()->create(['title' => 'Tomorrow, cancelled', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $past = evpProps(evpGet('member', evpSpaceUrl($space, '?scope=past'))->assertOk());
    expect($past['eventsScope'])->toBe('past')->and(evpTitles($past))->toBe(['Last week', 'Two weeks ago']);

    foreach (['', '?scope=upcoming', '?scope=everything', '?scope[]=past'] as $query) {
        $upcoming = evpProps(evpGet('member', evpSpaceUrl($space, $query))->assertOk());
        expect($upcoming['eventsScope'])->toBe('upcoming')->and(evpTitles($upcoming))->toBe(['Tomorrow, cancelled', 'Published members']);
    }
});

test('the drafts come first for the host, the owner and an admin, and the others get none', function () {
    $space = $this->spaces['members'];
    // Earlier than the draft: only the state puts the draft first
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['title' => 'Tomorrow', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    EventModel::factory()->inSpace($space)->hostedBy($this->c['member'])->draft()->create(['title' => 'Draft of the member', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    $list = fn (string $reader) => evpTitles(evpProps(evpGet($reader, evpSpaceUrl($space))->assertOk()));

    expect($list('owner'))->toBe(['Draft of the member', 'Draft members', 'Tomorrow', 'Published members'])
        ->and($list('admin'))->toBe(['Draft of the member', 'Draft members', 'Tomorrow', 'Published members'])
        ->and($list('host'))->toBe(['Draft members', 'Tomorrow', 'Published members'])
        ->and($list('member'))->toBe(['Draft of the member', 'Tomorrow', 'Published members'])
        ->and($list('moderator'))->toBe(['Tomorrow', 'Published members']);

    foreach (['outsider', 'visitor'] as $reader) {
        expect(evpTitles(evpProps(evpGet($reader, evpSpaceUrl($this->spaces['public']))->assertOk())))->toBe(['Published public']);
    }
});

test('a past draft is in the scope "past" for its host, the owner and an admin, and for nobody else', function () {
    $space = $this->spaces['public'];
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->draft()->past()->create(['title' => 'Past draft']);
    EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['title' => 'Two weeks ago', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addHour()]);
    $list = fn (string $reader, string $query) => evpTitles(evpProps(evpGet($reader, evpSpaceUrl($space, $query))->assertOk()));

    // The newest start first: the state gives a draft no other place in "Past"
    foreach (['host', 'owner', 'admin'] as $reader) {
        expect($list($reader, '?scope=past'))->toBe(['Past draft', 'Two weeks ago'], $reader);
    }
    foreach (['moderator', 'member', 'outsider', 'visitor'] as $reader) {
        expect($list($reader, '?scope=past'))->toBe(['Two weeks ago'], $reader);
    }
    // A draft that ended is in one list only
    expect($list('host', ''))->toBe(['Draft public', 'Published public'])
        ->and($list('member', ''))->toBe(['Published public']);
});

// ---------------------------------------------------------------------------------------------------------------------
// The event page
// ---------------------------------------------------------------------------------------------------------------------

test('the page of a published event is read by the same readers as its space', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpEventUrl($this->published[$visibility]), $expected[$reader]);
    }
})->with([
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 200, 'member' => 200, 'outsider' => 200, 'visitor' => 200]],
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 200, 'member' => 200, 'outsider' => 403, 'visitor' => 'login']],
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('a draft answers 404 to every reader but its host, the owner and the admins', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpEventUrl($this->drafts[$visibility]), $expected[$reader]);
    }
})->with([
    // The sign-in page is the answer of a page that does not exist, for a visitor (ADR 0044 point 4)
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 404, 'host' => 200, 'member' => 404, 'outsider' => 404, 'visitor' => 'login']],
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 404, 'host' => 200, 'member' => 404, 'outsider' => 403, 'visitor' => 'login']],
    // The space is refused before the event is looked for
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('the event page gives the event with its description, its host and its space', function () {
    $event = $this->published['members'];

    evpGet('member', evpEventUrl($event))->assertOk()->assertInertia(fn ($page) => $page->component('Events/Show')
        ->where('event.cuid', $event->cuid)
        ->where('event.title', 'Published members')
        ->where('event.body', $event->body)
        ->where('event.body_html', $event->body_html)
        ->where('event.state', 'published')
        ->where('event.is_past', false)
        ->where('event.time_zone', 'Europe/Brussels')
        ->where('event.host.cuid', $this->c['host']->cuid)
        ->has('event.host', 4)
        ->where('event.space.slug', $event->space->slug)
        ->where('event.can_view_location', true)
        ->where('event.meeting_url', EVP_MEETING_URL)
        ->where('event.place', EVP_PLACE)
        ->where('space.cuid', $event->space->cuid)
        ->where('community.cuid', $this->c['community']->cuid)
        ->has('spaceGroups')
        ->has('permissions', 5));
});

test('an event is only found in its own events space, and an address that names no event answers 404', function () {
    $event = $this->published['members'];
    $slug = $this->c['community']->slug;
    $discussion = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);

    evpGet('member', route('events.show', ['communitySlug' => $slug, 'spaceSlug' => $this->spaces['public']->slug, 'eventSlug' => $event->slug]))->assertNotFound();
    evpGet('member', route('events.show', ['communitySlug' => $slug, 'spaceSlug' => $discussion->slug, 'eventSlug' => $event->slug]))->assertNotFound();
    evpGet('member', route('events.show', ['communitySlug' => $slug, 'spaceSlug' => $event->space->slug, 'eventSlug' => 'no-such-event']))->assertNotFound();
    evpGet('member', route('events.edit', ['communitySlug' => $slug, 'spaceSlug' => $event->space->slug, 'eventSlug' => 'no-such-event']))->assertNotFound();
    evpGet('member', route('events.create', ['communitySlug' => $slug, 'spaceSlug' => $discussion->slug]))->assertNotFound();
    evpGet('visitor', route('events.show', ['communitySlug' => $slug, 'spaceSlug' => $this->spaces['public']->slug, 'eventSlug' => 'no-such-event']))->assertRedirect(route('login'));
});

test('an event with the slug "new" has its page, and the form of a new event keeps its address', function () {
    $event = EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->create(['title' => 'New']);
    expect($event->slug)->toBe('new');

    evpGet('member', evpEventUrl($event))->assertOk()->assertInertia(fn ($page) => $page->component('Events/Show')->where('event.cuid', $event->cuid));
    evpGet('member', evpCreateUrl($this->spaces['members']))->assertOk()->assertInertia(fn ($page) => $page->component('Events/Form')->where('event', null));
});

test('the permissions of the event page follow the policies, for each role', function (string $visibility, array $expected) {
    // "Answers" (#1515) is for the owner, the admins and the host: never a moderator, also with the right to delete content
    $none = ['canUpdate' => false, 'canCancel' => false, 'canDelete' => false, 'canDuplicate' => false, 'canViewAnswers' => false];
    $all = ['canUpdate' => true, 'canCancel' => true, 'canDelete' => true, 'canDuplicate' => true, 'canViewAnswers' => true];
    $named = ['all' => $all, 'none' => $none];

    foreach ($expected as $reader => $rights) {
        $props = evpProps(evpGet($reader, evpEventUrl($this->published[$visibility]))->assertOk());

        expect($props['permissions'])->toBe(is_string($rights) ? $named[$rights] : $rights, "{$reader} in the {$visibility} space");
    }
})->with([
    // Only the moderators create an event in the public space: the host, a plain member, cannot duplicate there
    'public' => ['public', [
        'owner' => 'all', 'admin' => 'all',
        'moderator' => ['canUpdate' => false, 'canCancel' => true, 'canDelete' => true, 'canDuplicate' => false, 'canViewAnswers' => false],
        'host' => ['canUpdate' => true, 'canCancel' => true, 'canDelete' => true, 'canDuplicate' => false, 'canViewAnswers' => true],
        'member' => 'none', 'outsider' => 'none', 'visitor' => 'none',
    ]],
    'members' => ['members', [
        'owner' => 'all', 'admin' => 'all',
        'moderator' => ['canUpdate' => false, 'canCancel' => true, 'canDelete' => true, 'canDuplicate' => false, 'canViewAnswers' => false],
        'host' => 'all', 'member' => 'none',
    ]],
    'groups' => ['groups', ['owner' => 'all', 'admin' => 'all', 'host' => 'all']],
]);

test('the page of a draft gives nobody the answers: not its host, not the owner, not an admin', function () {
    foreach (['host', 'owner', 'admin'] as $reader) {
        $props = evpProps(evpGet($reader, evpEventUrl($this->drafts['members']))->assertOk());

        expect($props['permissions']['canViewAnswers'])->toBeFalse("{$reader} of a draft")
            ->and($props['permissions']['canUpdate'])->toBeTrue("{$reader} of a draft");
    }
});

test('the page of a cancelled event still gives its host the answers', function () {
    $event = $this->published['members'];
    $event->update(['state' => KnowiiEventState::Cancelled]);

    expect(evpProps(evpGet('host', evpEventUrl($event))->assertOk())['permissions']['canViewAnswers'])->toBeTrue()
        ->and(evpProps(evpGet('moderator', evpEventUrl($event))->assertOk())['permissions']['canViewAnswers'])->toBeFalse()
        ->and(evpProps(evpGet('member', evpEventUrl($event))->assertOk())['permissions']['canViewAnswers'])->toBeFalse();
});

test('a host who left the community keeps no right on the event page', function () {
    $event = $this->published['public'];
    $this->c['community']->removeUser($this->c['host']);

    $props = evpProps(evpGet('host', evpEventUrl($event))->assertOk());

    // An outsider now: the public space stays readable, without a right and without the meeting address
    expect($props['permissions'])->toBe(['canUpdate' => false, 'canCancel' => false, 'canDelete' => false, 'canDuplicate' => false, 'canViewAnswers' => false])
        ->and($props['event']['meeting_url'])->toBeNull()
        ->and($props['event']['can_view_location'])->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// The form of the host
// ---------------------------------------------------------------------------------------------------------------------

test('the form of a new event opens for the people who may create an event in the space', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpCreateUrl($this->spaces[$visibility]), $expected[$reader]);
    }
})->with([
    // Posting policy "moderators"
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 403, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
    // Posting policy "members"
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 200, 'host' => 200, 'member' => 200, 'outsider' => 403, 'visitor' => 'login']],
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('the form of a published event opens for the owner, the admins and the host: 403 for the others who see it', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpEventUrl($this->published[$visibility], 'events.edit'), $expected[$reader]);
    }
})->with([
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('the form of a draft answers 404 to a reader who may not see the draft', function (string $visibility, array $expected) {
    foreach (EVP_READERS as $reader) {
        evpAssertAnswer($reader, evpEventUrl($this->drafts[$visibility], 'events.edit'), $expected[$reader]);
    }
})->with([
    'public' => ['public', ['owner' => 200, 'admin' => 200, 'moderator' => 404, 'host' => 200, 'member' => 404, 'outsider' => 404, 'visitor' => 'login']],
    'members' => ['members', ['owner' => 200, 'admin' => 200, 'moderator' => 404, 'host' => 200, 'member' => 404, 'outsider' => 403, 'visitor' => 'login']],
    // The space is refused before the event is looked for
    'groups' => ['groups', ['owner' => 200, 'admin' => 200, 'moderator' => 403, 'host' => 200, 'member' => 403, 'outsider' => 403, 'visitor' => 'login']],
]);

test('in a groups space, a member of the access group who is not the host gets 404 from a draft and from its form', function () {
    $draft = $this->drafts['groups'];
    // The member now reads the space, like the host: the draft stays for its host, the owner and the admins
    $draft->space->accessGroups()->firstOrFail()->members()->attach($this->c['member']);

    evpAssertAnswer('member', evpSpaceUrl($draft->space), 200);
    evpAssertAnswer('member', evpEventUrl($draft), 404);
    evpAssertAnswer('member', evpEventUrl($draft, 'events.edit'), 404);
    // A published event of the same space: they see it, and its form is not theirs
    evpAssertAnswer('member', evpEventUrl($this->published['groups']), 200);
    evpAssertAnswer('member', evpEventUrl($this->published['groups'], 'events.edit'), 403);
    expect(evpTitles(evpProps(evpGet('member', evpSpaceUrl($draft->space))->assertOk())))->toBe(['Published groups']);

    foreach (['host', 'owner', 'admin'] as $reader) {
        evpAssertAnswer($reader, evpEventUrl($draft, 'events.edit'), 200);
    }
});

test('the form gets the event with its description, the zones of the server and the rights of the reader', function () {
    $event = $this->published['members'];

    evpGet('host', evpEventUrl($event, 'events.edit'))->assertOk()->assertInertia(fn ($page) => $page->component('Events/Form')
        ->where('event.cuid', $event->cuid)
        ->where('event.body', $event->body)
        ->where('event.meeting_url', EVP_MEETING_URL)
        ->where('event.place', EVP_PLACE)
        ->where('event.host.cuid', $this->c['host']->cuid)
        ->where('space.cuid', $event->space->cuid)
        ->has('spaceGroups')
        ->where('timeZones', fn ($zones) => collect($zones)->contains('Europe/Brussels') && collect($zones)->contains('UTC') && ! collect($zones)->contains('Asia/Calcutta'))
        ->where('permissions', ['canNotifyNow' => false, 'canManageAllEvents' => false]));

    $rights = fn (string $reader) => evpProps(evpGet($reader, evpCreateUrl($this->spaces['members']))->assertOk())['permissions'];
    expect($rights('owner'))->toBe(['canNotifyNow' => true, 'canManageAllEvents' => true])
        ->and($rights('admin'))->toBe(['canNotifyNow' => true, 'canManageAllEvents' => true])
        ->and($rights('moderator'))->toBe(['canNotifyNow' => true, 'canManageAllEvents' => false])
        ->and($rights('member'))->toBe(['canNotifyNow' => false, 'canManageAllEvents' => false]);

    evpGet('member', evpCreateUrl($this->spaces['members']))->assertInertia(fn ($page) => $page->component('Events/Form')->where('event', null)->has('timeZones'));
});

// ---------------------------------------------------------------------------------------------------------------------
// The methods
// ---------------------------------------------------------------------------------------------------------------------

test('the pages of events only read: another method answers 404 and changes nothing', function (string $method) {
    $event = $this->published['members'];
    $before = EventModel::query()->count();

    foreach ([evpEventUrl($event), evpEventUrl($event, 'events.edit'), evpCreateUrl($event->space)] as $url) {
        $this->actingAs($this->c['owner'])->call($method, $url, ['title' => 'Changed', 'state' => 'cancelled'])->assertNotFound();
    }

    expect(EventModel::query()->count())->toBe($before)
        ->and($event->refresh()->title)->toBe('Published members')
        ->and($event->state->value)->toBe('published');
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

// ---------------------------------------------------------------------------------------------------------------------
// The meeting address and the place (design 7.3 point 2)
// ---------------------------------------------------------------------------------------------------------------------

test('no screen gives the meeting address or the place to a visitor, an outsider or a member of a "going" event', function () {
    $space = $this->spaces['public'];
    $going = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])
        ->create(['title' => 'Going only', 'meeting_url' => EVP_MEETING_URL, 'place' => EVP_PLACE, 'location_visibility' => KnowiiEventLocationVisibility::Going]);
    $scan = function (string $reader, string $url): string {
        $response = evpGet($reader, $url)->assertOk();

        // The whole page: the props, and everything else that the answer holds
        return (string) json_encode(evpProps($response)).$response->getContent();
    };
    $assertCut = function (string $text, string $what): void {
        // Not `toContain`: a failure would print the whole page
        foreach ([EVP_MEETING_URL, 'the-secret-room-of-the-pages', EVP_PLACE] as $secret) {
            expect(str_contains($text, $secret))->toBeFalse("{$what} holds \"{$secret}\"");
        }
    };

    // A visitor and an outsider: the list, the page of each event, and the public page of the community
    foreach (['visitor', 'outsider'] as $reader) {
        foreach (['list' => evpSpaceUrl($space), 'event' => evpEventUrl($this->published['public']), 'going event' => evpEventUrl($going), 'public page' => evpHomeUrl()] as $screen => $url) {
            $text = $scan($reader, $url);
            $assertCut($text, "{$screen} for a {$reader}");
            // The screen does hold the event: the scan reads something
            expect(str_contains($text, $screen === 'going event' ? 'Going only' : 'Published public'))->toBeTrue("{$screen} for a {$reader}");
        }
        $props = evpProps(evpGet($reader, evpEventUrl($this->published['public'])));
        expect($props['event']['can_view_location'])->toBeFalse()->and($props['event']['meeting_url'])->toBeNull()->and($props['event']['place'])->toBeNull();
    }

    // A member of a "going" event: its page never holds them; the lists hold them only for the other event
    $assertCut($scan('member', evpEventUrl($going)), 'the going event for a member');
    $assertCut($scan('moderator', evpEventUrl($going)), 'the going event for a moderator');
    foreach (['list' => evpSpaceUrl($space), 'home' => evpHomeUrl()] as $screen => $url) {
        $key = $screen === 'list' ? 'events' : 'nextEvents';
        $props = evpProps(evpGet('member', $url)->assertOk());
        $list = $props[$key];
        $items = (array) json_decode((string) json_encode(is_array($list) && array_key_exists('items', $list) ? $list['items'] : $list), true);
        $byTitle = array_column($items, null, 'title');

        expect($byTitle['Going only']['meeting_url'])->toBeNull("{$screen}")
            ->and($byTitle['Going only']['place'])->toBeNull()
            ->and($byTitle['Going only']['can_view_location'])->toBeFalse()
            ->and($byTitle['Published public']['meeting_url'])->toBe(EVP_MEETING_URL)
            ->and($byTitle['Published public']['place'])->toBe(EVP_PLACE);
    }

    // The host, the owner and an admin read them
    foreach (['host', 'owner', 'admin'] as $reader) {
        expect(str_contains($scan($reader, evpEventUrl($going)), 'the-secret-room-of-the-pages'))->toBeTrue("the going event for the {$reader}");
    }
});

test('the four screens give the address of a "going" event to a member who answered Yes, the host, the owner and an admin, and to nobody else (#1515)', function () {
    $space = $this->spaces['public'];
    $going = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])
        ->create(['title' => 'Going only', 'meeting_url' => EVP_MEETING_URL, 'place' => EVP_PLACE, 'location_visibility' => KnowiiEventLocationVisibility::Going, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    // The other events of the screens give their address to every member: without them, only the "going" event holds it
    EventModel::query()->whereKeyNot($going->id)->update(['meeting_url' => null, 'place' => null]);
    foreach (['yes' => 'Yes', 'replay' => 'Replay only', 'no' => 'No'] as $answer => $label) {
        $this->c[$answer] = User::factory()->withUserProfile()->create(['name' => "Member {$label}"]);
        $this->c['community']->users()->attach($this->c[$answer], ['role' => KnowiiCommunityMemberRole::Member]);
        EventAnswer::factory()->to($going)->of($this->c[$answer])->state(['answer' => $answer])->create();
    }
    // The list of the space, the page of the event, the home of a member, and the public page for the others
    $screens = ['list' => evpSpaceUrl($space), 'event' => evpEventUrl($going), 'home or public page' => evpHomeUrl()];
    $holds = function (string $reader, string $url): bool {
        $response = evpGet($reader, $url)->assertOk();
        $text = (string) json_encode(evpProps($response)).$response->getContent();
        // The screen does hold the event: the scan reads something
        expect(str_contains($text, 'Going only'))->toBeTrue("{$reader} on {$url}");

        return str_contains($text, 'the-secret-room-of-the-pages') || str_contains($text, EVP_PLACE);
    };

    foreach (['yes', 'host', 'owner', 'admin'] as $reader) {
        foreach ($screens as $screen => $url) {
            expect($holds($reader, $url))->toBeTrue("the {$screen} for {$reader}");
        }
    }
    foreach (['replay', 'no', 'member', 'moderator', 'outsider', 'visitor'] as $reader) {
        foreach ($screens as $screen => $url) {
            expect($holds($reader, $url))->toBeFalse("the {$screen} for {$reader}");
        }
    }
    // The form of the event is for the host, the owner and the admins, who read the address anyway
    expect($holds('host', evpEventUrl($going, 'events.edit')))->toBeTrue();
    evpAssertAnswer('yes', evpEventUrl($going, 'events.edit'), 403);

    // The member changes Yes to No: the address goes away on every screen. Then Yes again, and the member leaves
    EventAnswer::query()->where('event_id', $going->id)->where('user_id', $this->c['yes']->id)->update(['answer' => 'no']);
    foreach ($screens as $screen => $url) {
        expect($holds('yes', $url))->toBeFalse("the {$screen} after Yes became No");
    }
    EventAnswer::query()->where('event_id', $going->id)->where('user_id', $this->c['yes']->id)->update(['answer' => 'yes']);
    expect($holds('yes', evpEventUrl($going)))->toBeTrue();
    $this->c['community']->removeUser($this->c['yes']);
    $this->c['yes'] = User::findOrFail($this->c['yes']->id);
    foreach ($screens as $screen => $url) {
        expect($holds('yes', $url))->toBeFalse("the {$screen} after the member left");
    }
});

test('the screens give each reader their own answer and the places, and the limit only to the people who read the answers (#1515)', function () {
    $space = $this->spaces['members'];
    $event = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])
        ->create(['title' => 'With a limit', 'capacity' => 4321, 'replay_expected' => true, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    EventAnswer::factory()->to($event)->of($this->c['member'])->yes()->create();
    EventAnswer::factory()->to($event)->of($this->c['moderator'])->replay()->create();
    $fields = function (string $reader, string $url, string $key) use ($event): array {
        $props = evpProps(evpGet($reader, $url)->assertOk());
        $list = $key === 'event' ? [$props['event']] : $props[$key];
        $items = (array) json_decode((string) json_encode(is_array($list) && array_key_exists('items', $list) ? $list['items'] : $list), true);
        $found = array_column($items, null, 'cuid')[$event->cuid];

        return [$found['my_answer'], $found['can_answer'], $found['replay_expected'], $found['places_left'], $found['capacity']];
    };

    foreach (['events' => evpSpaceUrl($space), 'event' => evpEventUrl($event), 'nextEvents' => evpHomeUrl()] as $key => $url) {
        expect($fields('member', $url, $key))->toBe(['yes', true, true, 4320, null], "member, {$key}")
            ->and($fields('moderator', $url, $key))->toBe(['replay', true, true, 4320, null], "moderator, {$key}")
            ->and($fields('host', $url, $key))->toBe([null, true, true, 4320, 4321], "host, {$key}")
            ->and($fields('owner', $url, $key))->toBe([null, true, true, 4320, 4321], "owner, {$key}");
        // A member never reads the limit or the answer of another member, anywhere in the page
        $page = evpGet('member', $url);
        $text = (string) json_encode(evpProps($page)).$page->getContent();
        expect(str_contains($text, '4321'))->toBeFalse("member, {$key}")->and(str_contains($text, '"replay"'))->toBeFalse("member, {$key}");
    }
});

test('the list of a space and "Next events" run the same queries for 2 events and for 20: the answers and the places load once (#1515)', function (string $reader) {
    $space = $this->spaces['members'];
    // Only the events of this test: every reader then gets the same ones (a draft is in the lists of some readers only)
    EventModel::query()->delete();
    $add = function (int $number) use ($space): void {
        $event = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create([
            'title' => "Counted {$number}", 'capacity' => 10 + $number, 'replay_expected' => true,
            'location_visibility' => $number % 2 === 0 ? KnowiiEventLocationVisibility::Going : KnowiiEventLocationVisibility::Members,
            'starts_at' => now()->addDays($number), 'ends_at' => now()->addDays($number)->addHour(),
        ]);
        EventAnswer::factory()->to($event)->of($this->c['member'])->state(['answer' => $number % 2 === 0 ? 'yes' : 'no'])->create();
        EventAnswer::factory()->to($event)->of($this->c['moderator'])->yes()->create();
    };
    $queries = function (string $url, string $key) use ($reader): array {
        // A new instance of the reader: the one of the test keeps the relations that an earlier request loaded
        $this->c[$reader] = User::query()->findOrFail($this->c[$reader]->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $props = evpProps(evpGet($reader, $url)->assertOk());
        DB::disableQueryLog();
        $list = $props[$key];
        $items = (array) json_decode((string) json_encode(is_array($list) && array_key_exists('items', $list) ? $list['items'] : $list), true);

        return [count(DB::getQueryLog()), $items];
    };
    $screens = ['events' => evpSpaceUrl($space), 'nextEvents' => evpHomeUrl()];
    // 2 events
    $add(1);
    $add(2);
    // A first request fills what the application keeps for the next ones
    foreach ($screens as $key => $url) {
        $queries($url, $key);
    }
    $few = [];
    foreach ($screens as $key => $url) {
        [$few[$key], $items] = $queries($url, $key);
        expect($items)->toHaveCount(2, $key);
    }

    // 20 events: the list of the space gives them all, "Next events" the first 3
    foreach (range(3, 20) as $number) {
        $add($number);
    }
    foreach ($screens as $key => $url) {
        [$many, $items] = $queries($url, $key);
        // The fields that need the answers are there, for each event of the page
        $first = array_column($items, null, 'title')['Counted 1'];
        expect($items)->toHaveCount($key === 'events' ? 20 : 3, $key)
            // One Yes of a moderator for 11 places
            ->and($first['places_left'])->toBe(10, $key)
            ->and($first['my_answer'])->toBe($reader === 'member' ? 'no' : null, $key)
            ->and($many)->toBe($few[$key], "{$reader}, {$key}")
            // A fixed ceiling: a query per event would pass it at once
            ->and($many)->toBeLessThanOrEqual(EVP_MAX_QUERIES[$key], "{$reader}, {$key}: {$many}");
    }
})->with(['member', 'host', 'owner']);

// ---------------------------------------------------------------------------------------------------------------------
// "Next events"
// ---------------------------------------------------------------------------------------------------------------------

test('the home of a member gets the next 3 events of the events spaces that they can read, the next start first, never a draft', function () {
    $at = fn (int $days) => ['starts_at' => now()->addDays($days), 'ends_at' => now()->addDays($days)->addHour()];
    EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->cancelled()->create(['title' => 'In 1 day, cancelled', ...$at(1)]);
    EventModel::factory()->inSpace($this->spaces['public'])->hostedBy($this->c['host'])->create(['title' => 'In 2 days', ...$at(2)]);
    EventModel::factory()->inSpace($this->spaces['groups'])->hostedBy($this->c['host'])->create(['title' => 'In 3 days, groups', ...$at(3)]);
    EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['member'])->draft()->create(['title' => 'Draft of the member', ...$at(1)]);
    EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->past()->create(['title' => 'Last week']);
    $next = fn (string $reader) => evpTitles(evpProps(evpGet($reader, evpHomeUrl())->assertOk()), 'nextEvents');

    expect(Constants::NEXT_EVENTS)->toBe(3)
        // The member does not read the groups space, and gets no draft, their own included
        ->and($next('member'))->toBe(['In 1 day, cancelled', 'In 2 days', 'Published public'])
        ->and($next('moderator'))->toBe(['In 1 day, cancelled', 'In 2 days', 'Published public'])
        // The host and the owner read the groups space; still no draft
        ->and($next('host'))->toBe(['In 1 day, cancelled', 'In 2 days', 'In 3 days, groups'])
        ->and($next('owner'))->toBe(['In 1 day, cancelled', 'In 2 days', 'In 3 days, groups']);

    $first = (array) json_decode((string) json_encode(evpProps(evpGet('member', evpHomeUrl()))['nextEvents']), true)[0];
    expect($first['state'])->toBe('cancelled')
        ->and($first['space']['slug'])->toBe($this->spaces['members']->slug)
        ->and($first['body'])->toBeNull();
});

test('the public page gets the next events of the public events spaces only, for a visitor and an outsider', function (string $reader) {
    $at = fn (int $days) => ['starts_at' => now()->addDays($days), 'ends_at' => now()->addDays($days)->addHour()];
    EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->create(['title' => 'In 1 day, members', ...$at(1)]);
    foreach ([2, 3, 4] as $days) {
        EventModel::factory()->inSpace($this->spaces['public'])->hostedBy($this->c['host'])->create(['title' => "In {$days} days", ...$at($days)]);
    }

    $response = evpGet($reader, evpHomeUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Public'));
    $props = evpProps($response);

    expect(evpTitles($props, 'nextEvents'))->toBe(['In 2 days', 'In 3 days', 'In 4 days'])
        ->and((string) json_encode($props))->not->toContain('In 1 day, members')->not->toContain('Draft public');
})->with(['visitor', 'outsider']);

test('a community without an upcoming event gets an empty list of next events', function () {
    EventModel::query()->delete();

    expect(evpTitles(evpProps(evpGet('member', evpHomeUrl())->assertOk()), 'nextEvents'))->toBe([])
        ->and(evpProps(evpGet('member', evpHomeUrl()))['nextEvents'])->toBe([])
        ->and(evpTitles(evpProps(evpGet('visitor', evpHomeUrl())->assertOk()), 'nextEvents'))->toBe([]);
});
