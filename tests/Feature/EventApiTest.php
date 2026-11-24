<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Events\DeletesEvents;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

/*
 * The API of events (#1513, ADR 0053): each route, the token abilities, what a visitor and an outsider read, and the
 * meeting address and the place that never reach a reader who may not see them.
 */

const EVA_MEETING_URL = 'https://meet.example.com/very-secret-room';

const EVA_PLACE = 'Back room of the old library';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->spaces = [
        'public' => Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]),
        'members' => Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Members]),
    ];
    $this->published = [];
    $this->drafts = [];
    foreach ($this->spaces as $visibility => $space) {
        $this->published[$visibility] = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->create(['title' => "Published {$visibility}", 'meeting_url' => EVA_MEETING_URL, 'place' => EVA_PLACE]);
        $this->drafts[$visibility] = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->draft()->create(['title' => "Draft {$visibility}", 'meeting_url' => EVA_MEETING_URL, 'place' => EVA_PLACE]);
    }
    $this->going = EventModel::factory()->inSpace($this->spaces['public'])->hostedBy($this->c['moderator'])->create(['title' => 'Going only', 'meeting_url' => EVA_MEETING_URL, 'place' => EVA_PLACE, 'location_visibility' => KnowiiEventLocationVisibility::Going]);
    $this->pastEvent = EventModel::factory()->inSpace($this->spaces['public'])->hostedBy($this->c['moderator'])->past()->create(['title' => 'Last week']);
});

/**
 * @param  array<string, string>  $more
 */
function evaPath(string $route, Space $space, ?EventModel $event = null, array $more = []): string
{
    return ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ...($event === null ? [] : [ApiRoutes::PARAM_EVENT => $event->cuid]),
        ...$more,
    ]);
}

function evaCommunityPath(): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_EVENTS, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid]);
}

function evaRead(string $reader, string $url): TestResponse
{
    $test = test();
    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return $test->getJson($url);
    }

    return $test->actingAs($test->c[$reader])->getJson($url);
}

/**
 * @return list<string>
 */
function evaTitles(TestResponse $response): array
{
    return array_column((array) $response->json('data'), 'title');
}

// ---------------------------------------------------------------------------------------------------------------------
// Each route with a session
// ---------------------------------------------------------------------------------------------------------------------

test('a moderator creates, reads, changes, publishes, duplicates and deletes an event through the API', function () {
    $space = $this->spaces['public'];
    $created = $this->actingAs($this->c['moderator'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), [
        'title' => 'Weekly review office hour',
        'body' => 'Bring **questions**',
        'starts_at' => '2026-11-18T17:00:00+01:00',
        'ends_at' => '2026-11-18T18:00:00+01:00',
        'time_zone' => 'Europe/Brussels',
        'meeting_url' => 'https://meet.example.com/abc',
    ])->assertCreated()
        ->assertJsonPath('data.state', 'draft')
        ->assertJsonPath('data.starts_at', '2026-11-18T16:00:00.000000Z')
        ->assertJsonPath('data.ends_at', '2026-11-18T17:00:00.000000Z')
        ->assertJsonPath('data.time_zone', 'Europe/Brussels')
        ->assertJsonPath('data.is_past', false)
        ->assertJsonPath('data.can_view_location', true)
        ->assertJsonPath('data.meeting_url', 'https://meet.example.com/abc')
        ->assertJsonPath('data.body_html', '<p>Bring <strong>questions</strong></p>')
        ->assertJsonPath('data.host.cuid', $this->c['moderator']->cuid)
        ->assertJsonPath('data.space.cuid', $space->cuid)
        // The community of the event (#1515)
        ->assertJsonPath('data.community.cuid', $this->c['community']->cuid)
        ->assertJsonPath('data.cover', null);
    expect(array_keys((array) $created->json('data')))->toBe([
        'cuid', 'title', 'slug', 'state', 'starts_at', 'ends_at', 'time_zone', 'is_past', 'location_visibility', 'can_view_location',
        'meeting_url', 'place', 'my_answer', 'can_answer', 'replay_expected', 'places_left', 'capacity', 'cover', 'cover_alt', 'host', 'space', 'community', 'body', 'body_html', 'can_view_replay', 'replay_url', 'replay', 'notes', 'notes_html', 'created_at', 'updated_at',
    ]);
    $event = EventModel::query()->where('cuid', $created->json('data.cuid'))->firstOrFail();

    $this->actingAs($this->c['moderator'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertOk()->assertJsonPath('data.body', 'Bring **questions**');
    $this->actingAs($this->c['moderator'])->patchJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event), ['title' => 'A better title'])->assertOk()
        ->assertJsonPath('data.title', 'A better title')->assertJsonPath('data.slug', 'weekly-review-office-hour');
    $this->actingAs($this->c['moderator'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $event), ['state' => 'published'])->assertOk()
        ->assertJsonPath('data.state', 'published');
    $copy = $this->actingAs($this->c['moderator'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE, $space, $event))->assertCreated()
        ->assertJsonPath('data.state', 'draft')->assertJsonPath('data.starts_at', '2026-11-25T16:00:00.000000Z');
    $this->actingAs($this->c['moderator'])->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertNoContent();

    expect(EventModel::query()->whereKey($event->id)->exists())->toBeFalse()
        ->and(EventModel::query()->where('cuid', $copy->json('data.cuid'))->exists())->toBeTrue();
});

test('the field errors of the times are 400, a refused business rule is 422, a missing right is 403', function () {
    $space = $this->spaces['public'];
    $path = evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space);
    $input = ['title' => 'An event', 'starts_at' => '2026-11-18T17:00:00+01:00', 'ends_at' => '2026-11-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels'];

    $this->actingAs($this->c['moderator'])->postJson($path, [...$input, 'starts_at' => '2026-11-18T17:00:00+02:00'])->assertStatus(400)->assertJsonPath('errors.starts_at.0', 'This offset is not the offset of the time zone at this time');
    $this->actingAs($this->c['moderator'])->postJson($path, [...$input, 'starts_at' => '2026-11-18T17:00:00'])->assertStatus(400);
    $this->actingAs($this->c['moderator'])->postJson($path, [...$input, 'starts_at' => '2027-03-28T02:30:00+01:00', 'ends_at' => '2027-03-28T04:00:00+02:00'])->assertStatus(400)
        ->assertJsonPath('errors.starts_at.0', 'This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.');
    $this->actingAs($this->c['moderator'])->postJson($path, [...$input, 'time_zone' => 'Mars/Olympus'])->assertStatus(400);
    $this->actingAs($this->c['moderator'])->postJson($path, [...$input, 'notify_now' => 'soon'])->assertStatus(400);
    // Only moderators post in this space
    $this->actingAs($this->c['member'])->postJson($path, $input)->assertForbidden();

    $started = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->create(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()]);
    $this->actingAs($this->c['moderator'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $started), ['state' => 'draft'])->assertStatus(422);
    $this->actingAs($this->c['moderator'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $this->drafts['public']), ['state' => 'cancelled'])->assertStatus(422);
    $this->actingAs($this->c['moderator'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $this->published['public']), ['state' => 'published'])->assertOk();
    // The same state again changes nothing
    expect($this->published['public']->refresh()->sequence)->toBe(0);
});

test('notify_now through the API is for the people who moderate the space', function () {
    $space = $this->spaces['members'];
    $input = ['title' => 'Now', 'starts_at' => '2026-11-18T17:00:00+01:00', 'ends_at' => '2026-11-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels', 'state' => 'published', 'notify_now' => true];

    $this->actingAs($this->c['member'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), $input)->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), $input)->assertCreated();
});

test('the list gives upcoming events by default, the drafts first, then the next start first, and past events newest first; another scope is a field error', function () {
    $space = $this->spaces['public'];
    $later = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->create(['title' => 'Later', 'starts_at' => now()->addWeeks(3), 'ends_at' => now()->addWeeks(3)->addHour()]);
    $soon = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->cancelled()->create(['title' => 'Soon, cancelled', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $older = EventModel::factory()->inSpace($space)->hostedBy($this->c['moderator'])->create(['title' => 'Two weeks ago', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addHour()]);

    $upcoming = $this->actingAs($this->c['member'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space))->assertOk();
    expect(evaTitles($upcoming))->toBe(['Soon, cancelled', 'Published public', 'Going only', 'Later'])
        ->and($upcoming->json('metadata.pagination.totalCount'))->toBe(4)
        ->and($upcoming->json('data.0.body'))->toBeNull()
        ->and($upcoming->json('data.0.state'))->toBe('cancelled');

    // The drafts first, for the people who may see them (#1514): the host, then the owner
    foreach (['moderator', 'owner'] as $role) {
        expect(evaTitles($this->actingAs($this->c[$role])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space))->assertOk()))
            ->toBe(['Draft public', 'Soon, cancelled', 'Published public', 'Going only', 'Later']);
    }

    $past = $this->actingAs($this->c['member'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space).'?scope=past')->assertOk();
    expect(evaTitles($past))->toBe(['Last week', 'Two weeks ago'])
        ->and($past->json('data.0.is_past'))->toBeTrue();

    $page = $this->actingAs($this->c['member'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space).'?limit=1&offset=1')->assertOk();
    expect(evaTitles($page))->toBe(['Published public'])->and($page->json('metadata.pagination.nextOffset'))->toBe(2);

    $this->actingAs($this->c['member'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space).'?scope=everything')->assertStatus(400)->assertJsonPath('errors.scope.0', 'Use upcoming or past');
    expect([$later->id, $soon->id, $older->id])->toHaveCount(3);
});

test('a draft is listed for its host, the owner and the admins only, and never in the list of the community', function () {
    $space = $this->spaces['members'];
    $list = fn (string $role) => evaTitles(evaRead($role, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space))->assertOk());

    expect($list('member'))->toBe(['Published members'])
        ->and($list('moderator'))->toContain('Draft members')
        ->and($list('admin'))->toContain('Draft members')
        ->and($list('owner'))->toContain('Draft members');

    $own = EventModel::factory()->inSpace($space)->hostedBy($this->c['member'])->draft()->create(['title' => 'My draft']);
    expect($list('member'))->toContain('My draft')->not->toContain('Draft members');

    foreach (['owner', 'admin', 'moderator', 'member'] as $role) {
        $titles = evaTitles(evaRead($role, evaCommunityPath())->assertOk());
        expect($titles)->not->toContain('Draft members')->not->toContain('Draft public')->not->toContain('My draft');
    }
    expect($own->exists)->toBeTrue();
});

test('the list of the community holds the upcoming events of every events space that the reader can read', function () {
    $member = evaRead('member', evaCommunityPath())->assertOk();
    expect(evaTitles($member))->toEqualCanonicalizing(['Published public', 'Published members', 'Going only'])
        ->and(evaTitles($member))->not->toContain('Last week');

    expect(evaTitles(evaRead('visitor', evaCommunityPath())->assertOk()))->toEqualCanonicalizing(['Published public', 'Going only'])
        ->and(evaTitles(evaRead('outsider', evaCommunityPath())->assertOk()))->toEqualCanonicalizing(['Published public', 'Going only']);
});

test('the list of the community has the guards of the public page: 404 to a visitor and 403 to an outsider without a public space', function () {
    $this->spaces['public']->update(['visibility' => KnowiiSpaceVisibility::Members]);

    evaRead('visitor', evaCommunityPath())->assertNotFound();
    evaRead('outsider', evaCommunityPath())->assertForbidden();
    evaRead('member', evaCommunityPath())->assertOk();
});

test('a visitor and an outsider read a public events space, and get the answer of a space or an event that does not exist elsewhere', function () {
    foreach (['visitor', 'outsider', 'member'] as $reader) {
        evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $this->spaces['public']))->assertOk();
        evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['public'], $this->published['public']))->assertOk()
            ->assertJsonPath('data.host.name', $this->c['moderator']->name)
            ->assertJsonPath('data.body', $this->published['public']->body);
    }
    expect(evaTitles(evaRead('visitor', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $this->spaces['public']))))->not->toContain('Draft public');

    // A members space, and a draft in a public space: the answer of a space or an event that does not exist
    evaRead('visitor', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $this->spaces['members']))->assertNotFound();
    evaRead('outsider', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $this->spaces['members']))->assertNotFound();
    evaRead('visitor', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['members'], $this->published['members']))->assertNotFound();
    // A signed-in person who cannot read the space: the event does not exist for them (see the test of the 404 below)
    evaRead('outsider', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['members'], $this->published['members']))->assertNotFound();
    evaRead('visitor', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['public'], $this->drafts['public']))->assertNotFound();
    evaRead('moderator', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['public'], $this->drafts['public']))->assertOk();
});

test('the read of a draft answers 404 to a reader who cannot see it, as the writes do (ADR 0053, point 9)', function () {
    $missing = EventModel::factory()->inSpace($this->spaces['public'])->make(['cuid' => 'nothing-here']);

    foreach (['public', 'members'] as $visibility) {
        $space = $this->spaces[$visibility];
        foreach (['member', 'outsider'] as $reader) {
            $ofDraft = evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $this->drafts[$visibility]))->assertNotFound();
            $ofMissing = evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $missing))->assertNotFound();

            expect($ofDraft->getContent())->toBe($ofMissing->getContent(), "{$reader}, {$visibility}");
        }
    }
    // An admin hosts this draft: a moderator does not see it either
    $this->drafts['public']->update(['host_id' => $this->c['admin']->id]);
    evaRead('moderator', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['public'], $this->drafts['public']))->assertNotFound();
    // The people who see the draft read it
    foreach (['admin', 'owner'] as $reader) {
        evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['public'], $this->drafts['public']))->assertOk()->assertJsonPath('data.state', 'draft');
    }
});

test('the read of a published event of a space that the reader cannot read answers as an event that does not exist, or with the refusal of the space for a member', function () {
    $groups = Space::factory()->inGroup($this->spaces['members']->spaceGroup)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $groups->accessGroups()->attach($buyers);
    $forBuyers = EventModel::factory()->inSpace($groups)->hostedBy($this->c['owner'])->create(['title' => 'For the buyers']);
    $cancelled = EventModel::factory()->inSpace($groups)->hostedBy($this->c['owner'])->cancelled()->create(['title' => 'Cancelled for the buyers']);

    // A member and a moderator without the access group get the refusal of the space (403), a signed-in person who is not
    // a member the answer of an event that does not exist (404), in a "groups" and in a "members" space: the same answer
    // for an event that exists and for one that does not
    $cases = [
        ['member', $groups, $forBuyers, 403],
        ['member', $groups, $cancelled, 403],
        ['moderator', $groups, $forBuyers, 403],
        ['outsider', $groups, $forBuyers, 404],
        ['outsider', $this->spaces['members'], $this->published['members'], 404],
    ];
    foreach ($cases as $index => [$reader, $space, $event, $status]) {
        $missing = EventModel::factory()->inSpace($space)->make(['cuid' => 'nothing-here']);
        $ofHidden = evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertStatus($status);
        $ofMissing = evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $missing))->assertStatus($status);

        expect($ofHidden->getContent())->toBe($ofMissing->getContent(), "case {$index}: {$reader}")
            ->and((string) $ofHidden->getContent())->not->toContain('the buyers')->not->toContain($event->cuid);
    }

    // The people who read the space read the event: the owner, an admin, and a member with the access group
    $buyers->members()->attach($this->c['member']);
    $this->c['member'] = User::query()->findOrFail($this->c['member']->id);
    foreach (['owner', 'admin', 'member'] as $reader) {
        evaRead($reader, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $groups, $forBuyers))->assertOk()->assertJsonPath('data.title', 'For the buyers');
    }
});

test('a visitor gets no write route', function (string $method, string $route) {
    app('auth')->forgetGuards();
    $this->json($method, evaPath($route, $this->spaces['public'], $route === ApiRoutes::COMMUNITY_SPACE_EVENTS ? null : $this->published['public']))->assertUnauthorized();
})->with([
    'create' => ['POST', ApiRoutes::COMMUNITY_SPACE_EVENTS],
    'change' => ['PATCH', ApiRoutes::COMMUNITY_SPACE_EVENT],
    'delete' => ['DELETE', ApiRoutes::COMMUNITY_SPACE_EVENT],
    'state' => ['PUT', ApiRoutes::COMMUNITY_SPACE_EVENT_STATE],
    'duplicate' => ['POST', ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE],
]);

test('another space type gives 422, an event of another space gives 404', function () {
    $discussion = Space::factory()->inGroup($this->spaces['public']->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion]);

    $this->actingAs($this->c['owner'])->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $discussion))->assertStatus(422);
    $this->actingAs($this->c['owner'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $discussion), [
        'title' => 'Not here', 'starts_at' => '2026-11-18T17:00:00+01:00', 'ends_at' => '2026-11-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels',
    ])->assertStatus(422)->assertJsonPath('message', 'Events can only be created in events spaces');

    $wrong = evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->spaces['members'], $this->published['public']);
    $this->actingAs($this->c['owner'])->getJson($wrong)->assertNotFound();
    $this->actingAs($this->c['owner'])->patchJson($wrong, ['title' => 'Moved'])->assertNotFound();
    $this->actingAs($this->c['owner'])->deleteJson($wrong)->assertNotFound();
});

// ---------------------------------------------------------------------------------------------------------------------
// Tokens
// ---------------------------------------------------------------------------------------------------------------------

test('a read-only token reads events and never writes them', function () {
    Sanctum::actingAs($this->c['moderator'], ['events:read']);
    $space = $this->spaces['public'];
    $event = $this->published['public'];

    $this->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space))->assertOk();
    $this->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertOk();
    $this->getJson(evaCommunityPath())->assertOk();

    $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), ['title' => 'By token'])->assertForbidden();
    $this->patchJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event), ['title' => 'By token'])->assertForbidden();
    $this->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertForbidden();
    $this->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $event), ['state' => 'cancelled'])->assertForbidden();
    $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE, $space, $event))->assertForbidden();
});

test('a token without events:read reads no event, and events:write writes', function () {
    Sanctum::actingAs($this->c['moderator'], ['posts:read', 'communities:read']);
    $this->getJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $this->spaces['public']))->assertForbidden();
    $this->getJson(evaCommunityPath())->assertForbidden();

    Sanctum::actingAs($this->c['moderator'], ['events:write']);
    $this->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $this->spaces['public'], $this->published['public']), ['state' => 'cancelled'])->assertOk();
});

test('events:read is in the default set of a new token, events:write is not', function () {
    expect(Jetstream::$defaultPermissions)->toContain('events:read')->not->toContain('events:write')
        ->and(Jetstream::$permissions)->toContain('events:read', 'events:write');
});

// ---------------------------------------------------------------------------------------------------------------------
// The meeting address and the place (rule 49)
// ---------------------------------------------------------------------------------------------------------------------

test('the JSON of every event route holds neither the address nor the place for a visitor, an outsider, or a member of a going event', function () {
    $public = $this->spaces['public'];
    $reads = [
        'visitor' => [evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $public), evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $public).'?scope=past', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->published['public']), evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->going), evaCommunityPath()],
        'outsider' => [evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $public), evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->published['public']), evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->going), evaCommunityPath()],
    ];

    foreach ($reads as $reader => $urls) {
        foreach ($urls as $url) {
            $json = (string) evaRead($reader, $url)->assertOk()->getContent();
            expect($json)->not->toContain('very-secret-room', "{$reader} {$url}")
                ->not->toContain('old library', "{$reader} {$url}")
                ->not->toContain('"can_view_location":true', "{$reader} {$url}");
        }
    }

    // A member of a going event: the event and the lists that hold it
    $member = (string) evaRead('member', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->going))->assertOk()->getContent();
    expect($member)->not->toContain('very-secret-room')->not->toContain('old library')->toContain('"can_view_location":false');
    $list = evaRead('member', evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $public))->assertOk()->json('data');
    $byTitle = array_column((array) $list, null, 'title');
    expect($byTitle['Going only']['meeting_url'])->toBeNull()->and($byTitle['Going only']['place'])->toBeNull()
        ->and($byTitle['Published public']['meeting_url'])->toBe(EVA_MEETING_URL)->and($byTitle['Published public']['place'])->toBe(EVA_PLACE);
    $community = evaRead('member', evaCommunityPath())->assertOk()->json('data');
    expect(array_column((array) $community, 'meeting_url', 'title')['Going only'])->toBeNull();

    // The host, the owner and the admins read the address of a going event
    foreach (['moderator', 'owner', 'admin'] as $role) {
        evaRead($role, evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $public, $this->going))->assertOk()->assertJsonPath('data.meeting_url', EVA_MEETING_URL)->assertJsonPath('data.place', EVA_PLACE);
    }
});

test('a person who may not see an event gets 404 from its write routes, as for an event that does not exist', function () {
    $space = $this->spaces['public'];
    $draft = $this->drafts['public'];
    $missing = EventModel::factory()->inSpace($space)->make(['cuid' => 'nothing-here']);

    foreach ([$draft, $missing] as $event) {
        $this->actingAs($this->c['member'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $event), ['state' => 'cancelled'])->assertNotFound();
        $this->actingAs($this->c['member'])->patchJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event), ['title' => 'Seen'])->assertNotFound();
        $this->actingAs($this->c['member'])->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $event))->assertNotFound();
        $this->actingAs($this->c['member'])->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE, $space, $event))->assertNotFound();
    }
    // An admin hosts this draft: a moderator does not see it either
    $draft->update(['host_id' => $this->c['admin']->id]);
    $this->actingAs($this->c['moderator'])->putJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $space, $draft), ['state' => 'cancelled'])->assertNotFound();
    $this->actingAs($this->c['moderator'])->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $space, $draft))->assertNotFound();
    expect($draft->refresh()->state->value)->toBe('draft');
});

test('creating and duplicating events is limited per member', function () {
    RateLimiter::clear('events');
    $space = $this->spaces['public'];
    $input = ['title' => 'An event', 'starts_at' => '2026-11-18T17:00:00+01:00', 'ends_at' => '2026-11-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels'];
    $this->actingAs($this->c['moderator']);

    foreach (range(1, Constants::EVENTS_PER_MINUTE - 1) as $i) {
        $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), [...$input, 'title' => "Event {$i}"])->assertCreated();
    }
    $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE, $space, $this->published['public']))->assertCreated();

    $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENTS, $space), $input)->assertStatus(429);
    $this->postJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_DUPLICATE, $space, $this->published['public']))->assertStatus(429);
});

// Cancel it first (#1516, rules 40 to 43)
// ---------------------------------------------------------------------------------------------------------------------

/**
 * An event of the members space for the "cancel it first" rule, with one answer of a member
 */
function evaDeleteCase(string $case): EventModel
{
    $test = test();
    $factory = EventModel::factory()->inSpace($test->spaces['members'])->hostedBy($test->c['moderator']);
    $member = User::factory()->withUserProfile()->create();
    $test->c['community']->users()->attach($member, ['role' => 'member']);
    $groups = $case === 'lost access' ? evaGroupsSpace() : null;
    $event = match ($case) {
        'now' => $factory->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]),
        'ended' => $factory->past()->create(),
        'cancelled' => $factory->cancelled()->create(),
        'draft' => $factory->draft()->create(),
        'lost access' => $factory->inSpace($groups ?? $test->spaces['members'])->create(),
        default => $factory->create(),
    };
    $answer = match ($case) {
        'replay' => 'replay',
        'no' => 'no',
        'nobody' => null,
        default => 'yes',
    };
    $buyers = $groups?->accessGroups()->first();
    $buyers?->members()->attach($member->id);
    if ($answer !== null) {
        EventAnswer::factory()->to($event)->of($member)->{$answer}()->create();
    }
    // The member lost the access group of the space: the answer stays and counts for nothing
    $buyers?->members()->detach($member->id);

    return $event;
}

function evaGroupsSpace(): Space
{
    $test = test();
    $space = Space::factory()->inGroup($test->spaces['members']->spaceGroup)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $test->c['community']->id, 'name' => 'EVA buyers']);
    $space->accessGroups()->attach($buyers);
    $buyers->members()->attach($test->c['moderator']->id);

    return $space;
}

test('a published event that members still expect cannot be deleted: cancel it first, through the action and through DELETE', function (string $case) {
    $event = evaDeleteCase($case);

    expect(fn () => app(DeletesEvents::class)->delete($this->c['owner'], $event))
        ->toThrow(BusinessException::class, 'Cancel it first, so members are told.');
    $this->actingAs($this->c['moderator'])->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event->space, $event))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cancel it first, so members are told.');

    expect(EventModel::query()->whereKey($event->id)->exists())->toBeTrue()
        ->and(EventAnswer::query()->where('event_id', $event->id)->count())->toBe(1);
})->with([
    'a Yes' => ['yes'],
    'a Replay only' => ['replay'],
    'an event that happens now' => ['now'],
]);

test('a draft, a cancelled event, an event that ended, a No alone, no answer and the answer of a member who lost the access do not block a delete', function (string $case) {
    $event = evaDeleteCase($case);

    $this->actingAs($this->c['moderator'])->deleteJson(evaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event->space, $event))
        ->assertNoContent();

    expect(EventModel::query()->whereKey($event->id)->exists())->toBeFalse()
        ->and(EventAnswer::query()->where('event_id', $event->id)->count())->toBe(0);
})->with([
    'a draft' => ['draft'],
    'a cancelled event' => ['cancelled'],
    'an event that ended' => ['ended'],
    'a No alone' => ['no'],
    'no answer' => ['nobody'],
    'a Yes of a member who lost the access' => ['lost access'],
]);

test('the rule of the delete reads the row of the event inside a lock, and still needs the right to delete', function () {
    $event = evaDeleteCase('nobody');
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements) {
        $statements[] = strtolower($query->sql);
    });

    expect(fn () => app(DeletesEvents::class)->delete($this->c['member'], $event))->toThrow(AuthorizationException::class);
    app(DeletesEvents::class)->delete($this->c['moderator'], $event);

    expect(collect($statements)->contains(fn (string $sql) => preg_match('/from "events" where .* for update$/', $sql) === 1))->toBeTrue();
});
