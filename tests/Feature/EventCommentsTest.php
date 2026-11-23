<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Events\DeletesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Event as EventModel;
use App\Models\Mention;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/*
 * The comments of an event, slice E3 (#1516, ADR 0053, "Comments and after the event"): the routes with each reader, the
 * order of the refusals, a draft, a member who lost the access, the scoped bindings, the markers, the reactions on a
 * comment, no reaction on the event itself, the check of the table, the cascades, the return to draft and the live
 * updates.
 */

beforeEach(function () {
    // A fixed now: 10 November 2026, 09:00 UTC. The published events of the tests are a week later
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $this->c = communityWithMembers();
    $this->c['host'] = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($this->c['host'], ['role' => KnowiiCommunityMemberRole::Member]);
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->spaces = [
        'public' => Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Members]),
        'members' => Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Members]),
    ];
    $this->events = [];
    foreach ($this->spaces as $visibility => $space) {
        $this->events[$visibility] = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['title' => "Office hour {$visibility}"]);
    }
});

/**
 * The address of the comments of an event, of one comment, or of the reactions of one comment
 */
function ecmPath(EventModel $event, ?Comment $comment = null, string $suffix = ''): string
{
    return ApiRoutes::path($comment === null ? ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENTS : ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $event->community->cuid,
        ApiRoutes::PARAM_SPACE => $event->space->cuid,
        ApiRoutes::PARAM_EVENT => $event->cuid,
        ApiRoutes::PARAM_COMMENT => $comment?->cuid,
    ])).$suffix;
}

function ecmComment(string|User $author, EventModel $event, string $body = 'See you there', ?Comment $parent = null): Comment
{
    return app(CreatesComments::class)->create($author instanceof User ? $author : test()->c[$author], $event, ['body' => $body, 'parent_cuid' => $parent?->cuid]);
}

/**
 * A new request of the reader: a visitor, or the account as the database holds it now
 */
function ecmAs(?string $reader): void
{
    if ($reader === null) {
        app('auth')->forgetGuards();

        return;
    }
    test()->actingAs(User::query()->findOrFail(test()->c[$reader]->id));
}

/**
 * The status of each route of the comments of an event for one reader. The comment of the member is the one to change.
 *
 * @return array<string, int>
 */
function ecmStatuses(?string $reader, EventModel $event): array
{
    $comment = ecmComment('member', $event, 'The first comment');
    $statuses = [];
    $run = function (string $route, Closure $request) use ($reader, &$statuses): void {
        ecmAs($reader);
        /** @var TestResponse $response */
        $response = $request();
        $statuses[$route] = $response->status();
        // A refusal never says anything of the comments
        if ($response->status() >= 400) {
            expect($response->getContent())->not->toContain('The first comment');
        }
    };

    $run('list', fn () => test()->getJson(ecmPath($event)));
    $run('create', fn () => test()->postJson(ecmPath($event), ['body' => 'A new comment']));
    $run('react', fn () => test()->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍']));
    $run('who reacted', fn () => test()->getJson(ecmPath($event, $comment, '/reactions')));
    $run('update', fn () => test()->patchJson(ecmPath($event, $comment), ['body' => 'Changed']));
    $run('delete', fn () => test()->deleteJson(ecmPath($event, $comment)));

    return $statuses;
}

// The readers and the routes (rules 5, 6, 10, 12 and 50)
// ---------------------------------------------------------------------------------------------------------------------

test('each reader gets the answer of rule 10 from each route of the comments of an event', function (?string $reader, string $visibility, array $expected) {
    expect(ecmStatuses($reader, $this->events[$visibility]))->toBe($expected);
})->with([
    // A visitor reads the comments of a public space, and writes nothing
    'a visitor, public space' => [null, 'public', ['list' => 200, 'create' => 401, 'react' => 401, 'who reacted' => 401, 'update' => 401, 'delete' => 401]],
    'a visitor, members space' => [null, 'members', ['list' => 401, 'create' => 401, 'react' => 401, 'who reacted' => 401, 'update' => 401, 'delete' => 401]],
    // A signed-in person who is not a member reads a public space (the comments and who reacted) and is refused a write;
    // elsewhere the event does not exist
    'an outsider, public space' => ['outsider', 'public', ['list' => 200, 'create' => 403, 'react' => 403, 'who reacted' => 200, 'update' => 403, 'delete' => 403]],
    'an outsider, members space' => ['outsider', 'members', ['list' => 404, 'create' => 404, 'react' => 404, 'who reacted' => 404, 'update' => 404, 'delete' => 404]],
    // The author edits and deletes
    'the author' => ['member', 'members', ['list' => 200, 'create' => 201, 'react' => 200, 'who reacted' => 200, 'update' => 200, 'delete' => 204]],
    // A moderator with the right to delete content deletes, and does not edit
    'a moderator' => ['moderator', 'members', ['list' => 200, 'create' => 201, 'react' => 200, 'who reacted' => 200, 'update' => 403, 'delete' => 204]],
    // The host comments as a member: hosting gives no right on the comments of others
    'the host' => ['host', 'members', ['list' => 200, 'create' => 201, 'react' => 200, 'who reacted' => 200, 'update' => 403, 'delete' => 403]],
    'an admin' => ['admin', 'members', ['list' => 200, 'create' => 201, 'react' => 200, 'who reacted' => 200, 'update' => 403, 'delete' => 204]],
    'the owner' => ['owner', 'members', ['list' => 200, 'create' => 201, 'react' => 200, 'who reacted' => 200, 'update' => 403, 'delete' => 204]],
]);

test('members comment an event and reply, through the API', function () {
    $event = $this->events['members'];
    ecmAs('member');

    $created = $this->postJson(ecmPath($event), ['body' => 'Worth **joining** <script>x</script>'])->assertCreated();
    expect($created->json('data.body_html'))->toContain('<strong>joining</strong>')->not->toContain('<script>');
    ecmAs('admin');
    $this->postJson(ecmPath($event), ['body' => 'Agreed', 'parent_cuid' => $created->json('data.cuid')])->assertCreated();

    $list = $this->getJson(ecmPath($event))->assertOk();
    expect($list->json('metadata.totalCount'))->toBe(2)
        ->and($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.replies.0.body'))->toBe('Agreed');

    $comment = Comment::whereCuid($created->json('data.cuid'))->firstOrFail();
    expect($comment->event_id)->toBe($event->id)
        ->and($comment->post_id)->toBeNull()
        ->and($comment->community_resource_id)->toBeNull()
        ->and($comment->community_id)->toBe($this->c['community']->id)
        ->and($comment->subject()->is($event))->toBeTrue();
});

test('a visitor reads the comments of an event of a public space with the authors as names only', function () {
    ecmComment('member', $this->events['public'], 'Read by anyone');

    $response = $this->getJson(ecmPath($this->events['public']))->assertOk();

    expect($response->json('data.0.body'))->toBe('Read by anyone')
        ->and($response->json('data.0.author.cuid'))->toBe($this->c['member']->cuid)
        ->and($response->getContent())->not->toContain($this->c['member']->email);
});

// A draft, a cancelled event, a past event (rules 7, 8 and 10)
// ---------------------------------------------------------------------------------------------------------------------

test('a draft takes no comment, also from its host, the owner and an admin (422)', function (string $reader) {
    $draft = EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->draft()->create();
    ecmAs($reader);

    $this->postJson(ecmPath($draft), ['body' => 'Too early'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This event is a draft. It takes no comments.');
    // The people who see the draft read its list: it is empty
    $this->getJson(ecmPath($draft))->assertOk()->assertJsonPath('data', []);

    expect(Comment::query()->count())->toBe(0);
})->with(['host', 'owner', 'admin']);

test('a draft does not exist for a member who may not see it, and a visitor gets the answer of the public reads', function () {
    $draft = EventModel::factory()->inSpace($this->spaces['public'])->hostedBy($this->c['host'])->draft()->create();

    ecmAs('member');
    $this->postJson(ecmPath($draft), ['body' => 'Hello'])->assertNotFound();
    $this->getJson(ecmPath($draft))->assertNotFound();
    ecmAs('outsider');
    $this->postJson(ecmPath($draft), ['body' => 'Hello'])->assertNotFound();
    ecmAs(null);
    $this->getJson(ecmPath($draft))->assertUnauthorized();
    $this->postJson(ecmPath($draft), ['body' => 'Hello'])->assertUnauthorized();
});

test('a cancelled event and an event that ended take comments', function (string $state) {
    $event = match ($state) {
        'cancelled' => EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->cancelled()->create(),
        default => EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->past()->create(),
    };
    ecmAs('member');

    $this->postJson(ecmPath($event), ['body' => 'Thank you'])->assertCreated();
})->with(['cancelled', 'past']);

test('a new comment reads the state of the event inside a shared lock of its row, before the write', function () {
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements) {
        $statements[] = strtolower($query->sql);
    });

    ecmComment('member', $this->events['members']);

    $first = fn (string $pattern): ?int => array_key_first(array_filter($statements, static fn (string $sql): bool => preg_match($pattern, $sql) === 1));
    $lock = $first('/from "events" where .* for share$/');
    $write = $first('/^insert into "comments"/');
    expect($lock)->not->toBeNull()->and($write)->toBeGreaterThan($lock);
});

// A member who lost the access (rules 11 and 52)
// ---------------------------------------------------------------------------------------------------------------------

test('a member who lost the access group of a groups space gets 404 on every route, also for their own comment, which stays', function () {
    $space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $space->accessGroups()->attach($buyers);
    $buyers->members()->attach([$this->c['member']->id, $this->c['host']->id]);
    $event = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['title' => 'For the buyers']);
    $comment = ecmComment(User::query()->findOrFail($this->c['member']->id), $event, 'Mine');

    $buyers->members()->detach($this->c['member']->id);
    ecmAs('member');

    $this->getJson(ecmPath($event))->assertNotFound();
    $this->postJson(ecmPath($event), ['body' => 'Again'])->assertNotFound();
    $this->patchJson(ecmPath($event, $comment), ['body' => 'Changed'])->assertNotFound();
    $this->deleteJson(ecmPath($event, $comment))->assertNotFound();
    $this->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍'])->assertNotFound();
    $this->getJson(ecmPath($event, $comment, '/reactions'))->assertNotFound();

    // The comment stays under the name of its author, and in the export of that member
    $kept = $comment->fresh();
    expect($kept->body)->toBe('Mine')
        ->and($kept->author_id)->toBe($this->c['member']->id);
    $export = app(ExportsUsers::class)->export(User::query()->findOrFail($this->c['member']->id));
    $zip = new ZipArchive;
    $zip->open($export);
    $comments = json_decode((string) $zip->getFromName('comments.json'), true);
    $zip->close();
    @unlink($export);
    expect(collect($comments['comments'])->firstWhere('cuid', $comment->cuid))->toMatchArray([
        'post' => null,
        'resource' => null,
        'event' => ['cuid' => $event->cuid, 'title' => 'For the buyers'],
    ]);
});

test('a member who left the community gets 404 on every route of the comments of an event of a members space', function () {
    $event = $this->events['members'];
    $comment = ecmComment('member', $event, 'Mine');
    $this->c['community']->removeUser($this->c['member']);
    ecmAs('member');

    $this->getJson(ecmPath($event))->assertNotFound();
    $this->postJson(ecmPath($event), ['body' => 'Again'])->assertNotFound();
    $this->patchJson(ecmPath($event, $comment), ['body' => 'Changed'])->assertNotFound();
    $this->deleteJson(ecmPath($event, $comment))->assertNotFound();
    $this->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍'])->assertNotFound();
    $this->getJson(ecmPath($event, $comment, '/reactions'))->assertNotFound();

    expect($comment->fresh()?->body)->toBe('Mine');
});

// The scoped bindings (rule 13)
// ---------------------------------------------------------------------------------------------------------------------

test('a comment of another event, of a post or of a resource is not reachable through the address of this event', function () {
    $event = $this->events['members'];
    $other = EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->create();
    $ofOther = ecmComment('member', $other, 'On the other event');
    $post = Post::factory()->inSpace(Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]))->create();
    $ofPost = app(CreatesComments::class)->create($this->c['member'], $post, ['body' => 'On a post']);
    $resource = CommunityResource::factory()->create(['space_id' => Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Resources])->id]);
    $ofResource = app(CreatesComments::class)->create($this->c['member'], $resource, ['body' => 'On a resource']);
    ecmAs('member');

    foreach ([$ofOther, $ofPost, $ofResource] as $comment) {
        $this->patchJson(ecmPath($event, $comment), ['body' => 'x'])->assertNotFound();
        $this->deleteJson(ecmPath($event, $comment))->assertNotFound();
        $this->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍'])->assertNotFound();
        $this->getJson(ecmPath($event, $comment, '/reactions'))->assertNotFound();
    }
    // A reply to the comment of another event is a field error
    $this->postJson(ecmPath($event), ['body' => 'x', 'parent_cuid' => $ofOther->cuid])->assertStatus(400);

    expect($ofOther->fresh()->body)->toBe('On the other event');
});

// Markers, delete_replies and reactions (rule 12)
// ---------------------------------------------------------------------------------------------------------------------

test('a comment of an event with a reply of another member stays as a marker, and a moderator deletes it with its replies', function () {
    $event = $this->events['members'];
    $comment = ecmComment('member', $event, 'The question');
    ecmComment('admin', $event, 'The answer', $comment);

    ecmAs('member');
    $this->deleteJson(ecmPath($event, $comment))->assertOk();
    $marker = $comment->fresh();
    expect($marker->isDeleted())->toBeTrue()->and($marker->body)->toBe('')->and(Comment::query()->count())->toBe(2);

    // Nobody deletes a marker again without its replies; a member never has delete_replies
    $this->deleteJson(ecmPath($event, $comment))->assertForbidden();
    $this->deleteJson(ecmPath($event, $comment), ['delete_replies' => true])->assertForbidden();
    ecmAs('moderator');
    $this->deleteJson(ecmPath($event, $comment), ['delete_replies' => true])->assertNoContent();

    expect(Comment::query()->count())->toBe(0);
});

test('members react to the comments of an event and read who reacted', function () {
    $event = $this->events['members'];
    $comment = ecmComment('member', $event);

    ecmAs('admin');
    $this->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍'])
        ->assertOk()->assertJsonPath('data', [['emoji' => '👍', 'count' => 1, 'reacted' => true]]);
    $this->getJson(ecmPath($event, $comment, '/reactions'))->assertOk()->assertJsonPath('data.0.members.0.cuid', $this->c['admin']->cuid);
});

test('the event itself takes no reaction: no route, and the table of the reactions has no column for an event', function () {
    $event = $this->events['members'];
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [
        ApiRoutes::PARAM_COMMUNITY => $event->community->cuid,
        ApiRoutes::PARAM_SPACE => $event->space->cuid,
        ApiRoutes::PARAM_EVENT => $event->cuid,
    ]).'/reactions';
    ecmAs('member');

    expect($this->postJson($path, ['emoji' => '👍'])->status())->toBeIn([404, 405])
        ->and(Schema::hasColumn('reactions', 'event_id'))->toBeFalse()
        ->and(collect(app('router')->getRoutes()->getRoutes())->map->uri()->filter(fn (string $uri) => str_ends_with($uri, '{event}/reactions'))->all())->toBe([]);
});

// The table (rules 1 and 2)
// ---------------------------------------------------------------------------------------------------------------------

test('a comment belongs to exactly one post, one resource or one event', function (array $items) {
    $post = Post::factory()->inSpace(Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]))->create();
    $values = ['post' => ['post_id' => $post->id], 'event' => ['event_id' => $this->events['members']->id], 'none' => []];

    Comment::query()->insert([
        'cuid' => 'one-item-check',
        'community_id' => $this->c['community']->id,
        'author_id' => $this->c['member']->id,
        'body' => 'x',
        'body_html' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
        ...array_merge(...array_map(fn (string $item) => $values[$item], $items)),
    ]);
})->with([
    'an event and a post' => [['event', 'post']],
    'none' => [['none']],
])->throws(QueryException::class);

test('a deleted event, space or community deletes the comments of its events, with their reactions and mentions', function (string $what) {
    $event = $this->events['members'];
    $this->c['admin']->forceFill(['username' => 'ecm-admin'])->save();
    $comment = ecmComment('member', $event, 'Hello @ecm-admin');
    ecmComment('admin', $event, 'Hi', $comment);
    $comment->reactions()->create(['community_id' => $comment->community_id, 'user_id' => $this->c['admin']->id, 'emoji' => '👍']);
    expect(Mention::query()->where('comment_id', $comment->id)->count())->toBe(1);

    match ($what) {
        'event' => app(DeletesEvents::class)->delete($this->c['host'], $event),
        'space' => $this->spaces['members']->delete(),
        'community' => $this->c['community']->delete(),
    };

    expect(Comment::query()->count())->toBe(0)
        ->and(Reaction::query()->count())->toBe(0)
        ->and(Mention::query()->count())->toBe(0);
})->with(['event', 'space', 'community']);

// The return to draft (rule 9)
// ---------------------------------------------------------------------------------------------------------------------

test('back to draft is refused when the event has a comment, a marker too, and allowed without', function () {
    $event = $this->events['members'];
    $comment = ecmComment('member', $event, 'The question');
    ecmComment('admin', $event, 'The answer', $comment);
    app(DeletesComments::class)->delete($this->c['member'], $comment);
    expect($comment->fresh()->isDeleted())->toBeTrue();
    app(DeletesComments::class)->delete($this->c['admin'], Comment::query()->where('parent_id', $comment->id)->firstOrFail());
    expect(Comment::query()->count())->toBe(1);

    expect(fn () => app(SetsEventStates::class)->setState($this->c['host'], $event, ['state' => 'draft']))
        ->toThrow(BusinessException::class, 'Members commented on this event. Cancel it instead.');
    expect($event->fresh()->state)->toBe(KnowiiEventState::Published);

    $quiet = EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->create();
    app(SetsEventStates::class)->setState($this->c['host'], $quiet, ['state' => 'draft']);
    expect($quiet->fresh()->state)->toBe(KnowiiEventState::Draft);
});

test('the return to draft looks for a comment inside the lock of the row of the event', function () {
    $event = $this->events['members'];
    $steps = [];
    DB::listen(function (QueryExecuted $query) use (&$steps) {
        $sql = strtolower($query->sql);
        if (preg_match('/from "events" where .* for update$/', $sql) === 1) {
            $steps[] = 'lock at level '.DB::transactionLevel();
        } elseif (str_contains($sql, 'from "comments"')) {
            $steps[] = 'comments at level '.DB::transactionLevel();
        }
    });
    $outside = DB::transactionLevel();

    app(SetsEventStates::class)->setState($this->c['host'], $event, ['state' => 'draft']);

    expect($steps)->toBe(['lock at level '.($outside + 1), 'comments at level '.($outside + 1)]);
});

// The live updates (rule 15)
// ---------------------------------------------------------------------------------------------------------------------

test('the live updates of a comment of an event carry the event and the comment, never a text', function () {
    $event = $this->events['members'];
    $comment = ecmComment('member', $event, 'A secret text');
    ecmAs('admin');
    $this->postJson(ecmPath($event, $comment, '/reactions'), ['emoji' => '👍'])->assertOk();
    ecmAs('member');
    $this->patchJson(ecmPath($event, $comment), ['body' => 'Another secret text'])->assertOk();
    $this->deleteJson(ecmPath($event, $comment))->assertNoContent();

    $sent = Event::dispatched(SpaceContentChanged::class)->map(fn (array $arguments) => [$arguments[0]->broadcastAs(), $arguments[0]->broadcastWith()])->all();
    $names = array_column($sent, 0);
    expect($names)->toBe([SpaceContentChanged::COMMENT_CREATED, SpaceContentChanged::REACTIONS_CHANGED, SpaceContentChanged::COMMENT_UPDATED, SpaceContentChanged::COMMENT_DELETED]);
    foreach ($sent as [, $payload]) {
        expect($payload['event_cuid'])->toBe($event->cuid)
            ->and($payload['comment_cuid'])->toBe($comment->cuid)
            ->and($payload['space_cuid'])->toBe($event->space->cuid)
            ->and($payload)->not->toHaveKeys(['post_cuid', 'resource_cuid'])
            ->and(json_encode($payload))->not->toContain('secret');
    }
});

// The event page (rule 31)
// ---------------------------------------------------------------------------------------------------------------------

test('the event page carries the comments and the permissions of the comments', function (?string $reader, string $visibility, bool $canComment, bool $canModerate) {
    $event = $this->events[$visibility];
    ecmComment('member', $event, 'On the page');
    ecmAs($reader);

    $this->get(route('events.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $event->space->slug, 'eventSlug' => $event->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('comments', 1)
            ->where('comments.0.body', 'On the page')
            ->where('permissions.canComment', $canComment)
            ->where('permissions.canModerate', $canModerate));
})->with([
    'a visitor of a public space' => [null, 'public', false, false],
    'an outsider of a public space' => ['outsider', 'public', false, false],
    'a member' => ['member', 'members', true, false],
    'the host' => ['host', 'members', true, false],
    'a moderator' => ['moderator', 'members', true, true],
    'an admin' => ['admin', 'members', true, true],
    'the owner' => ['owner', 'members', true, true],
]);

test('the page of a draft carries no comment and no right to comment', function () {
    $draft = EventModel::factory()->inSpace($this->spaces['members'])->hostedBy($this->c['host'])->draft()->create();
    ecmAs('host');

    $this->get(route('events.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $draft->space->slug, 'eventSlug' => $draft->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('comments', 0)->where('permissions.canComment', false));
});

// A deleted account (rule 54) and the migration (rules 1 and 2)
// ---------------------------------------------------------------------------------------------------------------------

test('the comments on events of a deleted account stay without an author, and go or stay as markers with the content', function (bool $withContent) {
    $event = $this->events['members'];
    $alone = ecmComment('member', $event, 'A comment without replies');
    $answered = ecmComment('member', $event, 'A comment that the admin answered');
    ecmComment('admin', $event, 'The answer of the admin', $answered);

    app(DeletesAccounts::class)->delete(User::query()->findOrFail($this->c['member']->id), ['delete_content' => $withContent]);

    if ($withContent) {
        // Deleted, and a marker where another member replied (ADR 0043)
        $marker = $answered->fresh();
        expect($alone->fresh())->toBeNull()
            ->and($marker?->isDeleted())->toBeTrue()
            ->and($marker?->body)->toBe('')
            ->and($marker?->author_id)->toBeNull();
    } else {
        // "Deleted member": the text stays without an author
        expect($alone->fresh()?->body)->toBe('A comment without replies')
            ->and($alone->fresh()?->author_id)->toBeNull()
            ->and($answered->fresh()?->isDeleted())->toBeFalse();
    }
    expect(Comment::query()->where('body', 'The answer of the admin')->exists())->toBeTrue();
})->with(['with its content' => [true], 'without its content' => [false]]);

test('the migration of the comments of events goes down, deleting the comments of events, and up again', function () {
    $migration = require database_path('migrations/2026_11_23_200000_add_event_comments.php');
    $post = Post::factory()->inSpace(Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]))->create();
    $ofPost = app(CreatesComments::class)->create($this->c['member'], $post, ['body' => 'On a post']);
    ecmComment('member', $this->events['members'], 'On an event');
    $check = fn (): string => (string) DB::selectOne("select pg_get_constraintdef(oid) as definition from pg_constraint where conname = 'comments_one_item_check'")->definition;

    $migration->down();
    expect(Schema::hasColumn('comments', 'event_id'))->toBeFalse()
        ->and(Comment::query()->pluck('id')->all())->toBe([$ofPost->id])
        ->and($check())->toContain('num_nonnulls(post_id, community_resource_id) = 1');

    $migration->up();
    expect(Schema::hasColumn('comments', 'event_id'))->toBeTrue()
        ->and($check())->toContain('num_nonnulls(post_id, community_resource_id, event_id) = 1');
    ecmComment('member', $this->events['members'], 'On an event again');
    expect(Comment::query()->whereNotNull('event_id')->count())->toBe(1);
});
