<?php

use App\Actions\Notifications\BuildCatchUp;
use App\Actions\Notifications\TrackSpaceVisits;
use App\Actions\Users\ExportUser;
use App\Actions\Users\RecordLastVisit;
use App\ApiRoutes;
use App\Constants;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Notifications\BuildsCatchUp;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Contracts\Pages\CreatesPages;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Resources\CreatesCommunityResources;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\RecordsLastVisits;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\SpaceVisit;
use App\Models\User;
use App\Support\SpaceTree;
use App\Support\TimeZones;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
 * "Where I am", server part (#1476, ADR 0048): the time zone of the account, the last visit date, the visits of spaces
 * behind the dots of the navigation, the "Catch up" data, the pop-up switch and the "messages" number of the tab title.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->talk = Space::factory()->inGroup($this->group)->create();
    $this->announce = Space::factory()->inGroup($this->group)->create(['posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->chat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    $this->link = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Link, 'url' => 'https://example.com/where']);
    $this->owner = $this->c['owner'];
    $this->moderator = $this->c['moderator'];
    // Plain members: the owner and the admins view every space
    $this->plain = User::factory()->withUserProfile()->create();
    $this->other = User::factory()->withUserProfile()->create();
    $this->community->users()->attach([$this->plain->id => ['role' => 'member'], $this->other->id => ['role' => 'member']]);
    $this->outsider = $this->c['outsider'];
});

/**
 * Act as another user in the same test
 */
function wiaAs(User $user): void
{
    app('auth')->forgetGuards();
    test()->flushSession();
    test()->actingAs($user);
}

function wiaCatchUpPath(Community $community, ?string $since = null): string
{
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_CATCH_UP, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    return $since === null ? $path : $path.'?since='.$since;
}

function wiaVisitPath(Community $community, Space $space): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_VISIT, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
}

function wiaPost(User $author, Space $space, string $title = 'A post', ?Carbon $at = null, string $body = 'The text of the post'): Post
{
    $post = Post::factory()->inSpace($space)->create(['author_id' => $author->id, 'title' => $title, 'body' => $body]);
    if ($at !== null) {
        $post->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    return $post;
}

function wiaComment(User $author, Post $post, ?Carbon $at = null, string $body = 'The text of the comment'): Comment
{
    $comment = Comment::factory()->onPost($post)->create(['author_id' => $author->id, 'body' => $body]);
    if ($at !== null) {
        $comment->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    return $comment;
}

function wiaNeedsYou(User $recipient, Comment $comment, ?Carbon $at = null): Notification
{
    $row = Notification::factory()->forComment($comment, $recipient)->create();
    if ($at !== null) {
        Notification::withoutTimestamps(fn () => $row->forceFill(['updated_at' => $at, 'created_at' => $at])->save());
    }

    return $row;
}

/**
 * The day a member joined the community (the pivot is not mass assignable: write the column)
 */
function wiaJoined(User $user, Carbon $at): void
{
    DB::table('community_members')->where('community_id', test()->community->id)->where('user_id', $user->id)->update(['created_at' => $at]);
}

function wiaSince(int $daysAgo = 10): string
{
    return now('UTC')->subDays($daysAgo)->toDateString();
}

// The contracts

test('the contracts are bound to the actions (ADR 0048)', function () {
    expect(app(BuildsCatchUp::class))->toBeInstanceOf(BuildCatchUp::class)
        ->and(app(TracksSpaceVisits::class))->toBeInstanceOf(TrackSpaceVisits::class)
        ->and(app(RecordsLastVisits::class))->toBeInstanceOf(RecordLastVisit::class);
});

// 1. The time zone

test('the member sets the time zone of the account, and it is in their own resource', function () {
    expect($this->plain->fresh()->time_zone)->toBeNull();

    $this->actingAs($this->plain)->putJson(ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), ['time_zone' => 'Europe/Brussels'])
        ->assertOk()
        ->assertJsonPath('data.time_zone', 'Europe/Brussels');
    expect($this->plain->fresh()->time_zone)->toBe('Europe/Brussels');

    $this->putJson(ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), ['time_zone' => 'UTC'])->assertOk();
    expect($this->plain->fresh()->time_zone)->toBe('UTC');

    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk()->assertJsonPath('data.time_zone', 'UTC');
});

test('an older name of a zone is accepted and stored under its current name (#1678)', function (string $older, string $current) {
    // A browser can still give the older name of a zone
    $this->actingAs($this->plain)->putJson(ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), ['time_zone' => $older])
        ->assertOk()
        ->assertJsonPath('data.time_zone', $current);
    expect($this->plain->fresh()->time_zone)->toBe($current);

    $this->plain->forceFill(['time_zone' => 'Asia/Tokyo'])->save();
    $this->put('/user/profile-information', ['email' => $this->plain->email, 'name' => 'Older Zone', 'time_zone' => $older]);
    expect($this->plain->fresh()->time_zone)->toBe($current);
})->with([
    'India' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'Ukraine' => ['Europe/Kiev', 'Europe/Kyiv'],
    'Vietnam' => ['Asia/Saigon', 'Asia/Ho_Chi_Minh'],
    'Argentina' => ['America/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
]);

test('the current name of a zone: itself, the current name of an older one, or null (#1678)', function () {
    expect(TimeZones::current('Europe/Brussels'))->toBe('Europe/Brussels')
        ->and(TimeZones::current('UTC'))->toBe('UTC')
        ->and(TimeZones::current('Asia/Calcutta'))->toBe('Asia/Kolkata')
        ->and(TimeZones::isValid('Europe/Kiev'))->toBeTrue()
        ->and(TimeZones::orNull('Asia/Saigon'))->toBe('Asia/Ho_Chi_Minh')
        ->and(TimeZones::identifiers())->toContain('Asia/Kolkata')->not->toContain('Asia/Calcutta');

    foreach (['Mars/Olympus', 'europe/brussels', '+02:00', 'CET+1', 'Etc/GMT+2', '', ' Europe/Paris', "Europe/Paris\n", null] as $bad) {
        expect(TimeZones::current($bad))->toBeNull()
            ->and(TimeZones::isValid($bad))->toBeFalse();
    }
});

test('an unknown time zone is refused and changes nothing', function () {
    $this->plain->forceFill(['time_zone' => 'Asia/Tokyo'])->save();
    $this->actingAs($this->plain);

    foreach (['Mars/Olympus', 'europe/brussels', '+02:00', '', null] as $bad) {
        $response = $this->putJson(ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), ['time_zone' => $bad]);
        expect($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
        expect($this->plain->fresh()->time_zone)->toBe('Asia/Tokyo');
    }

    $this->putJson(ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), [])->assertStatus(400);
    expect($this->plain->fresh()->time_zone)->toBe('Asia/Tokyo');
});

test('the time zone is in the shared account of the page, and in no resource that another person gets', function () {
    $this->plain->forceFill(['time_zone' => 'Pacific/Auckland'])->save();
    $post = wiaPost($this->plain, $this->talk, 'Written by the member with a zone');
    wiaComment($this->plain, $post);
    $needle = 'Pacific/Auckland';

    // The member sees it in the page props (auth.user) and in their own resource
    $this->actingAs($this->plain)->get("/community/{$this->community->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.time_zone', $needle));

    // Another member reads the member lists, the profile, the post with its author and its comments
    wiaAs($this->other);
    $reads = [
        $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))->assertOk()->getContent(),
        $this->get("/community/{$this->community->slug}/member/{$this->plain->username}")->assertOk()->getContent(),
        $this->get("/community/{$this->community->slug}/space/{$this->talk->slug}/post/{$post->slug}")->assertOk()->getContent(),
        $this->get("/community/{$this->community->slug}/space/{$this->talk->slug}")->assertOk()->getContent(),
    ];
    foreach ($reads as $content) {
        expect($content)->not->toContain($needle)->not->toContain('time_zone');
    }

    // The moderators and the owner do not see it either
    wiaAs($this->owner);
    expect($this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))->getContent())->not->toContain($needle);
});

test('the sign-up sends the zone: an invalid or absent value gives null and never an error', function () {
    foreach ([['time_zone' => 'Europe/Paris', 'expected' => 'Europe/Paris'], ['time_zone' => 'Asia/Calcutta', 'expected' => 'Asia/Kolkata'], ['time_zone' => 'Nowhere/Land', 'expected' => null], ['time_zone' => ['x'], 'expected' => null], ['expected' => null]] as $index => $case) {
        $expected = $case['expected'];
        unset($case['expected']);
        app('auth')->forgetGuards();
        $this->flushSession();

        // The page of the link of the sign-up sends the zone (ADR 0065)
        signUpThroughLink([
            'name' => 'Zoned User '.$index,
            'email' => "zoned$index@example.com",
            'password' => 'password',
            ...$case,
        ])->assertRedirect();

        expect(User::query()->where('email', "zoned$index@example.com")->firstOrFail()->time_zone)->toBe($expected);
    }
});

test('the profile form changes the zone only when it sends the field', function () {
    $this->plain->forceFill(['time_zone' => 'Asia/Tokyo'])->save();
    $this->actingAs($this->plain);

    $this->put('/user/profile-information', ['email' => $this->plain->email, 'name' => 'Same Zone']);
    expect($this->plain->fresh()->time_zone)->toBe('Asia/Tokyo');

    $this->put('/user/profile-information', ['email' => $this->plain->email, 'name' => 'Same Zone', 'time_zone' => 'Europe/Rome']);
    expect($this->plain->fresh()->time_zone)->toBe('Europe/Rome');

    $this->put('/user/profile-information', ['email' => $this->plain->email, 'name' => 'Same Zone', 'time_zone' => 'Nope/Nope']);
    expect($this->plain->fresh()->time_zone)->toBe('Europe/Rome');
});

test('the platform never changes the zone by itself: visits, API calls and a browser language leave it alone', function () {
    $this->plain->forceFill(['time_zone' => 'Asia/Tokyo'])->save();
    $this->actingAs($this->plain);

    $this->withHeaders(['Accept-Language' => 'fr-BE', 'Time-Zone' => 'Europe/Brussels'])->get("/community/{$this->community->slug}")->assertOk();
    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
    $this->putJson(wiaVisitPath($this->community, $this->talk))->assertOk();

    expect($this->plain->fresh()->time_zone)->toBe('Asia/Tokyo')
        ->and(TimeZones::isValid('UTC'))->toBeTrue()
        ->and(TimeZones::isValid('Europe/Brussels'))->toBeTrue()
        ->and(TimeZones::isValid('Mars/Olympus'))->toBeFalse();
});

// 5. The pop-up switch

test('the pop-up switch is on by default, changes with a request, and shows in the own account only', function () {
    $this->actingAs($this->plain)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk()->assertJsonPath('data.notification_popups', true);

    $this->putJson(ApiRoutes::path(ApiRoutes::USER_NOTIFICATION_SETTINGS), ['popups' => false])->assertOk()->assertJsonPath('data.notification_popups', false);
    expect($this->plain->fresh()->notification_popups)->toBeFalse();

    $this->putJson(ApiRoutes::path(ApiRoutes::USER_NOTIFICATION_SETTINGS), ['popups' => true])->assertOk();
    expect($this->plain->fresh()->notification_popups)->toBeTrue();

    $this->putJson(ApiRoutes::path(ApiRoutes::USER_NOTIFICATION_SETTINGS), ['popups' => 'maybe'])->assertStatus(400);
    $this->putJson(ApiRoutes::path(ApiRoutes::USER_NOTIFICATION_SETTINGS), [])->assertStatus(400);

    $this->plain->forceFill(['notification_popups' => false])->save();
    wiaAs($this->other);
    expect($this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))->getContent())->not->toContain('notification_popups');
});

test('the new routes need their ability, and there is no route to close the card alone', function () {
    $routes = [
        ['put', ApiRoutes::path(ApiRoutes::USER_TIME_ZONE), ['time_zone' => 'UTC'], 'user:write', 'user:read'],
        ['put', ApiRoutes::path(ApiRoutes::USER_NOTIFICATION_SETTINGS), ['popups' => false], 'notifications:write', 'notifications:read'],
        ['put', wiaVisitPath($this->community, $this->talk), [], 'notifications:write', 'notifications:read'],
        ['get', wiaCatchUpPath($this->community, wiaSince()), [], 'notifications:read', 'notifications:write'],
    ];

    foreach ($routes as [$method, $path, $body, $needs, $other]) {
        Sanctum::actingAs($this->plain, [$other, 'posts:read', 'posts:write', 'communities:read']);
        $this->json($method, $path, $body)->assertForbidden();
        Sanctum::actingAs($this->plain, [$needs]);
        $this->json($method, $path, $body)->assertSuccessful();
    }

    // The second sentence of rule 2.5 is not built: no route forgets the session value alone
    $this->actingAs($this->plain);
    expect(collect(Route::getRoutes()->getRoutes())->contains(fn ($route) => $route->uri() === 'api/v1/user/catch-up'))->toBeFalse();
});

// 2. The last visit date

test('a page request of a signed-in member writes today (UTC) once a day, and an API call writes nothing', function () {
    Carbon::setTestNow('2026-11-16 23:30:00');
    $this->actingAs($this->plain);
    expect($this->plain->fresh()->getRawOriginal('last_visit_on'))->toBeNull();

    // An API call and an asset do not count
    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
    expect($this->plain->fresh()->getRawOriginal('last_visit_on'))->toBeNull();

    $this->get("/community/{$this->community->slug}")->assertOk();
    expect(substr((string) $this->plain->fresh()->getRawOriginal('last_visit_on'), 0, 10))->toBe('2026-11-16');

    // The same day: no second write
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (str_starts_with(strtolower($query->sql), 'update "users"')) {
            $writes++;
        }
    });
    $this->get("/community/{$this->community->slug}")->assertOk();
    $this->get("/community/{$this->community->slug}/space/{$this->talk->slug}")->assertOk();
    expect($writes)->toBe(0);

    // The next day: one write
    Carbon::setTestNow('2026-11-17 00:05:00');
    $this->get("/community/{$this->community->slug}")->assertOk();
    expect($writes)->toBe(1)->and(substr((string) $this->plain->fresh()->getRawOriginal('last_visit_on'), 0, 10))->toBe('2026-11-17');
});

test('an Inertia visit counts as a page, a visitor writes nothing', function () {
    $response = $this->actingAs($this->plain)->get("/community/{$this->community->slug}", ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())]);
    $response->assertOk();
    expect($this->plain->fresh()->getRawOriginal('last_visit_on'))->not->toBeNull();

    app('auth')->forgetGuards();
    $before = DB::table('users')->whereNotNull('last_visit_on')->count();
    $this->get('/')->assertOk();
    expect(DB::table('users')->whereNotNull('last_visit_on')->count())->toBe($before);
});

test('the old date waits in the session at 7 days, not at 6, and the card is null without a date', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    // 6 days: no card
    $this->plain->forceFill(['last_visit_on' => '2026-11-10'])->save();
    $this->actingAs($this->plain)->get("/community/{$this->community->slug}")
        ->assertOk()
        ->assertSessionMissing(Constants::SESSION_CATCH_UP_SINCE)
        ->assertInertia(fn ($page) => $page->where('catchUp', null));

    // 7 days: the card, with the old date
    wiaAs($this->other);
    $this->other->forceFill(['last_visit_on' => '2026-11-09'])->save();
    $this->get("/community/{$this->community->slug}")
        ->assertOk()
        ->assertSessionHas(Constants::SESSION_CATCH_UP_SINCE, '2026-11-09')
        ->assertInertia(fn ($page) => $page->where('catchUp.since', '2026-11-09'));
    expect(substr((string) $this->other->fresh()->getRawOriginal('last_visit_on'), 0, 10))->toBe('2026-11-16');

    // The card stays while the session lasts, also on the next page (the date was replaced)
    $this->get("/community/{$this->community->slug}/space/{$this->talk->slug}")
        ->assertInertia(fn ($page) => $page->where('catchUp.since', '2026-11-09'));

    // A new account (no date): no card
    wiaAs($this->c['member']);
    $this->get("/community/{$this->community->slug}")
        ->assertOk()
        ->assertSessionMissing(Constants::SESSION_CATCH_UP_SINCE)
        ->assertInertia(fn ($page) => $page->where('catchUp', null));
});

test('"I am caught up" forgets the session value when the request has a session', function () {
    $this->actingAs($this->plain)->withSession([Constants::SESSION_CATCH_UP_SINCE => '2026-10-01'])
        ->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])
        ->postJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_CAUGHT_UP), [])
        ->assertOk();

    expect(session()->has(Constants::SESSION_CATCH_UP_SINCE))->toBeFalse();
});

test('the last visit date is in no resource, no page prop and no API answer, managers included', function () {
    // 3 days ago: the card (7 days) does not carry the date, so any sight of it in the reads below is a leak
    $needle = now('UTC')->subDays(3)->toDateString();
    $this->plain->forceFill(['last_visit_on' => $needle])->save();

    $this->actingAs($this->plain);
    $reads = [
        $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk()->getContent(),
        $this->get("/community/{$this->community->slug}")->assertOk()->getContent(),
        $this->get('/dashboard')->getContent(),
    ];
    // The date of the test run replaced it: put the old one back for the readers below
    $this->plain->forceFill(['last_visit_on' => $needle])->save();
    wiaAs($this->owner);
    $members = ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]);
    $reads[] = $this->getJson($members)->assertOk()->getContent();
    $reads[] = $this->get("/community/{$this->community->slug}/member/{$this->plain->username}")->assertOk()->getContent();
    $reads[] = $this->get("/community/{$this->community->slug}/settings")->getContent();

    foreach ($reads as $content) {
        expect($content)->not->toContain('last_visit_on')->not->toContain($needle);
    }
    expect(array_key_exists('last_visit_on', $this->plain->fresh()->toArray()))->toBeFalse()
        ->and(array_key_exists('last_visit_on', $this->plain->fresh()->toResource()->resolve()))->toBeFalse();
});

// 3. The "Catch up" data

test('since is required, a valid date, not in the future, at most 366 days back', function () {
    $this->actingAs($this->plain);
    $get = fn (?string $since) => $this->getJson(wiaCatchUpPath($this->community, $since));

    $get(null)->assertStatus(400);
    $get('yesterday')->assertStatus(400);
    $get('2026-13-45')->assertStatus(400);
    $get(now('UTC')->addDay()->toDateString())->assertStatus(400);
    $get(now('UTC')->subDays(367)->toDateString())->assertStatus(400);

    $get(now('UTC')->toDateString())->assertOk();
    $get(now('UTC')->subDays(366)->toDateString())->assertOk();
});

test('only a member of the community has the card', function () {
    $this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertUnauthorized();

    $this->actingAs($this->outsider)->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertForbidden();
    $this->actingAs($this->plain)->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk()
        ->assertJsonPath('data.has_more', false)
        ->assertJsonCount(0, 'data.lines');
});

test('the lines come in order: 2 that need me, 2 announcements, 1 thread, at most 5, and has_more says there is more', function () {
    $author = $this->c['member'];
    $thread = wiaPost($this->plain, $this->talk, 'My own thread');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $thread->id, 'state' => 'following', 'reason' => 'wrote']);

    // 3 rows that need me: the 2 newest show
    $rows = [];
    foreach ([30, 20, 10] as $minutes) {
        $comment = wiaComment($author, $thread, now()->subMinutes($minutes), "Reply $minutes");
        $rows[$minutes] = wiaNeedsYou($this->plain, $comment, now()->subMinutes($minutes));
    }
    // 3 announcements: the 2 newest show
    wiaPost($this->moderator, $this->announce, 'Announcement old', now()->subDays(3));
    wiaPost($this->moderator, $this->announce, 'Announcement middle', now()->subDays(2));
    wiaPost($this->moderator, $this->announce, 'Announcement new', now()->subDays(1));
    // Another thread, with more new comments than mine
    $busy = wiaPost($this->owner, $this->talk, 'The busy thread');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $busy->id, 'state' => 'following', 'reason' => 'manual']);
    foreach (range(1, 4) as $i) {
        wiaComment($author, $busy, now()->subHours(5 - $i), "Busy comment $i");
    }

    $this->actingAs($this->plain);
    $response = $this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk();
    $lines = $response->json('data.lines');

    expect($lines)->toHaveCount(5)
        ->and(array_column($lines, 'kind'))->toBe(['needs_you', 'needs_you', 'announcement', 'announcement', 'thread'])
        ->and($lines[0]['excerpt'])->toBe('Reply 10')
        ->and($lines[1]['excerpt'])->toBe('Reply 20')
        ->and($lines[2]['title'])->toBe('Announcement new')
        ->and($lines[3]['title'])->toBe('Announcement middle')
        ->and($lines[4]['title'])->toBe('The busy thread')
        ->and($lines[4]['excerpt'])->toBe('Busy comment 4')
        ->and($response->json('data.has_more'))->toBeTrue();

    // A line has what the client needs to show two lines and to open the item
    foreach ($lines as $line) {
        expect($line)->toHaveKeys(['kind', 'title', 'excerpt', 'space', 'author', 'date', 'target'])
            ->and($line['space'])->toHaveKeys(['cuid', 'slug', 'name'])
            ->and($line['author'])->toHaveKeys(['cuid', 'name', 'username'])
            ->and($line['target'])->toHaveKeys(['post_slug', 'post_cuid', 'comment_cuid', 'chat_message_cuid', 'thread_cuid', 'resource_cuid', 'resource_slug', 'page_slug']);
    }
    expect($lines[2]['target']['post_slug'])->not->toBeNull()
        ->and($lines[4]['target']['comment_cuid'])->not->toBeNull();

    // No count of what was missed anywhere in the answer
    expect($response->getContent())->not->toContain('count')->not->toContain('new_comments')->not->toContain('missed');
});

test('has_more is false when every source fits what is shown', function () {
    $post = wiaPost($this->plain, $this->talk, 'Mine');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => 'following', 'reason' => 'wrote']);
    wiaNeedsYou($this->plain, wiaComment($this->c['member'], $post));
    wiaPost($this->moderator, $this->announce, 'One announcement', now()->subDay());

    $this->actingAs($this->plain)->getJson(wiaCatchUpPath($this->community, wiaSince()))
        ->assertOk()
        ->assertJsonCount(3, 'data.lines')
        ->assertJsonPath('data.has_more', false);
});

test('no thread line when no thread has a new comment, and announcements are never left out because of their age', function () {
    $post = wiaPost($this->plain, $this->talk, 'Mine');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => 'following', 'reason' => 'wrote']);
    // A comment from before the date does not count
    wiaComment($this->c['member'], $post, now()->subDays(40));
    // An announcement 300 days old is still shown when the date goes back that far
    wiaPost($this->moderator, $this->announce, 'Very old news', now()->subDays(300));

    $this->actingAs($this->plain);
    $lines = $this->getJson(wiaCatchUpPath($this->community, wiaSince(30)))->assertOk()->json('data.lines');
    expect($lines)->toHaveCount(0);

    $lines = $this->getJson(wiaCatchUpPath($this->community, wiaSince(366)))->assertOk()->json('data.lines');
    expect(array_column($lines, 'kind'))->toBe(['announcement', 'thread'])
        ->and($lines[0]['title'])->toBe('Very old news');
});

test('the thread line holds the posts that I follow and the spaces at Everything, not a muted or stopped thread, not my own comments', function () {
    $author = $this->c['member'];
    $everything = Space::factory()->inGroup($this->group)->create();
    NotificationSpaceLevel::query()->create(['user_id' => $this->plain->id, 'space_id' => $everything->id, 'level' => KnowiiSpaceNotificationLevel::All]);

    // A post of a space at "mine" that I do not follow: not a candidate
    $unfollowed = wiaPost($author, $this->talk, 'Not followed');
    foreach (range(1, 6) as $i) {
        wiaComment($author, $unfollowed, now()->subHours($i));
    }
    // A post of a space at Everything: a candidate
    $open = wiaPost($author, $everything, 'In an Everything space');
    foreach (range(1, 2) as $i) {
        wiaComment($author, $open, now()->subHours($i), "Open $i");
    }
    // A muted thread and a stopped thread of the same space: left out
    foreach (['muted', 'stopped'] as $state) {
        $silenced = wiaPost($author, $everything, "A $state thread");
        NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $silenced->id, 'state' => $state, 'reason' => 'manual']);
        foreach (range(1, 5) as $i) {
            wiaComment($author, $silenced, now()->subHours($i));
        }
    }
    // A followed thread where only I comment: nothing new for me
    $mine = wiaPost($author, $this->talk, 'Only me');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $mine->id, 'state' => 'following', 'reason' => 'manual']);
    foreach (range(1, 5) as $i) {
        wiaComment($this->plain, $mine, now()->subHours($i));
    }

    $this->actingAs($this->plain);
    $response = $this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk();
    expect(array_column($response->json('data.lines'), 'title'))->toBe(['In an Everything space'])
        ->and($response->json('data.has_more'))->toBeFalse();
});

test('nothing of a blocked author shows, and a muted space gives no announcement', function () {
    $blocked = $this->c['member'];
    wiaPost($blocked, $this->talk, 'Followed thread of a blocked author');
    $post = wiaPost($this->owner, $this->talk, 'Thread with a blocked commenter');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => 'following', 'reason' => 'manual']);
    wiaComment($blocked, $post, now()->subHour(), 'Blocked words');
    wiaNeedsYou($this->plain, wiaComment($blocked, $post, now()->subHour(), 'Blocked reply'));

    // The announcements: one of the blocked author, one of the moderator
    $announceByBlocked = wiaPost($blocked, $this->announce, 'Announcement of a blocked author', now()->subDay());
    wiaPost($this->moderator, $this->announce, 'Announcement of a moderator', now()->subDay());

    app(BlocksMembers::class)->block($this->plain, $blocked);
    $this->actingAs($this->plain);
    $content = $this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk()->getContent();
    expect($content)->not->toContain('Blocked words')->not->toContain('Blocked reply')->not->toContain('Announcement of a blocked author')->not->toContain($blocked->username)
        ->and($content)->toContain('Announcement of a moderator');
    $announceByBlocked->delete();

    // A muted space gives no announcement
    NotificationSpaceLevel::query()->create(['user_id' => $this->plain->id, 'space_id' => $this->announce->id, 'level' => KnowiiSpaceNotificationLevel::None]);
    expect($this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk()->getContent())->not->toContain('Announcement of a moderator');
});

test('a space that the member cannot read gives no line', function () {
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $secret = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $secret->accessGroups()->attach($group);
    wiaPost($this->moderator, $secret, 'Secret announcement', now()->subDay());
    $thread = wiaPost($this->owner, $secret, 'Secret thread');
    wiaComment($this->moderator, $thread, now()->subHour(), 'Secret comment');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $thread->id, 'state' => 'following', 'reason' => 'manual']);
    // A row that needs me, about the secret space: the access check deletes it
    wiaNeedsYou($this->plain, wiaComment($this->moderator, $thread, now()->subHour(), 'Secret reply'));

    $content = $this->actingAs($this->plain)->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk()->getContent();
    expect($content)->not->toContain('Secret');

    // A member of the group reads it
    $this->other->accessGroups()->attach($group, ['manual' => true, 'by_rule' => false]);
    wiaAs($this->other);
    expect($this->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk()->getContent())->toContain('Secret announcement');
});

test('the catch-up data runs a fixed number of queries, whatever the number of spaces', function () {
    $count = function (): int {
        app('auth')->forgetGuards();
        test()->actingAs($this->plain);
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson(wiaCatchUpPath($this->community, wiaSince()))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $post = wiaPost($this->plain, $this->talk, 'Mine');
    NotificationFollow::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => 'following', 'reason' => 'wrote']);
    wiaComment($this->c['member'], $post);
    wiaPost($this->moderator, $this->announce, 'News', now()->subDay());
    $count();
    $few = $count();

    foreach (range(1, 9) as $i) {
        $space = Space::factory()->inGroup($this->group)->create(['posting_policy' => $i % 2 === 0 ? KnowiiSpacePostingPolicy::Moderators : KnowiiSpacePostingPolicy::Members]);
        wiaPost($this->moderator, $space, "Post $i", now()->subHours($i));
    }
    $many = $count();

    expect($many)->toBe($few);
});

// 4. Visits of spaces

test('a member who opens a space, a post, a page, a lesson or a resource has visited it', function () {
    $pages = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Pages]);
    $course = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Course]);
    $resources = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Resources]);
    $post = wiaPost($this->c['member'], $this->talk);
    $page = Page::factory()->inSpace($pages)->create();
    $lesson = Page::factory()->inSpace($course)->create();
    $resource = CommunityResource::factory()->create(['space_id' => $resources->id, 'community_id' => $this->community->id]);
    $base = "/community/{$this->community->slug}/space";

    $urls = [
        [$this->talk, "$base/{$this->talk->slug}"],
        [$this->announce, "$base/{$this->announce->slug}/post/".wiaPost($this->c['member'], $this->announce)->slug],
        [$pages, "$base/{$pages->slug}/page/{$page->slug}"],
        [$course, "$base/{$course->slug}/page/{$lesson->slug}"],
        [$resources, "$base/{$resources->slug}/resource/{$resource->slug}"],
    ];
    expect($post)->not->toBeNull();

    $this->actingAs($this->plain);
    foreach ($urls as [$space, $url]) {
        expect(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $space->id)->exists())->toBeFalse();
        $this->get($url)->assertOk();
        expect(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $space->id)->count())->toBe(1);
    }

    // One row per member and space: it is replaced, never added
    Carbon::setTestNow(Carbon::parse('2030-01-01 12:00:00'));
    $this->get($urls[0][1])->assertOk();
    expect(SpaceVisit::query()->where('user_id', $this->plain->id)->count())->toBe(5)
        ->and(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $this->talk->id)->first()->visited_at->equalTo(now()))->toBeTrue();
});

test('a visitor and a signed-in person who is not a member never get a row, on the three ways', function () {
    $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    wiaPost($this->c['member'], $public, 'Public post');
    $url = "/community/{$this->community->slug}/space/{$public->slug}";

    // A visitor reads the public space
    $this->get($url)->assertOk();
    // A signed-in outsider reads it too
    $this->actingAs($this->outsider)->get($url)->assertOk();
    // The API refuses the visit of the outsider
    $this->putJson(wiaVisitPath($this->community, $public))->assertForbidden();
    // Writing is refused for them as well (no row either)
    expect(app(TracksSpaceVisits::class)->visit($this->outsider, $public))->toBeFalse();

    expect(SpaceVisit::query()->count())->toBe(0);

    // The API without a session: 401
    app('auth')->forgetGuards();
    $this->putJson(wiaVisitPath($this->community, $public))->assertUnauthorized();
    expect(SpaceVisit::query()->count())->toBe(0);
});

test('the API visit writes the row, and refuses a space that the member cannot read', function () {
    $this->actingAs($this->plain)->putJson(wiaVisitPath($this->community, $this->talk))->assertOk();
    expect(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $this->talk->id)->exists())->toBeTrue();

    $secret = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $this->putJson(wiaVisitPath($this->community, $secret))->assertForbidden();
    expect(SpaceVisit::query()->where('space_id', $secret->id)->exists())->toBeFalse();

    // A space of another community gives a 404
    $elsewhere = Space::factory()->create();
    $this->putJson(wiaVisitPath($this->community, $elsewhere))->assertNotFound();
});

test('writing a post, a comment, a lesson or a resource sets the new content of the space and the visit of the author', function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $course = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Course]);
    $resources = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Resources]);
    $author = $this->c['admin'];

    $post = app(CreatesPosts::class)->create($author, $this->talk, ['title' => 'A new post', 'body' => 'Some words']);
    expect($this->talk->fresh()->last_content_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(SpaceVisit::query()->where('user_id', $author->id)->where('space_id', $this->talk->id)->exists())->toBeTrue();

    Carbon::setTestNow('2026-11-16 12:10:00');
    app(CreatesComments::class)->create($this->plain, $post, ['body' => 'A comment']);
    expect($this->talk->fresh()->last_content_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $this->talk->id)->exists())->toBeTrue();

    Carbon::setTestNow('2026-11-16 12:20:00');
    app(CreatesPages::class)->create($author, $course, ['title' => 'A lesson', 'body' => 'The lesson']);
    expect($course->fresh()->last_content_at?->toIso8601String())->toBe(now()->toIso8601String());

    // A section is a heading, not content
    Carbon::setTestNow('2026-11-16 12:30:00');
    app(CreatesPages::class)->create($author, $course, ['title' => 'A section', 'kind' => 'section']);
    expect($course->fresh()->last_content_at?->toIso8601String())->toBe(Carbon::parse('2026-11-16 12:20:00')->toIso8601String());

    Carbon::setTestNow('2026-11-16 12:40:00');
    Storage::fake('local');
    app(CreatesCommunityResources::class)->create($author, $resources, ['url' => 'https://example.com/an-article', 'level' => 'beginner', 'capture' => false]);
    expect($resources->fresh()->last_content_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(SpaceVisit::query()->where('user_id', $author->id)->where('space_id', $resources->id)->exists())->toBeTrue();
});

test('a chat message does not set the new content of a chat space', function () {
    $message = ChatMessage::factory()->create(['space_id' => $this->chat->id, 'community_id' => $this->community->id, 'author_id' => $this->c['member']->id]);
    expect($message)->not->toBeNull();
    app(CreatesChatMessages::class)->create($this->c['member'], $this->chat, ['body' => 'Hello chat']);

    expect($this->chat->fresh()->last_content_at)->toBeNull();
});

test('the navigation has has_new and notification_level for a member only', function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    wiaJoined($this->plain, now()->subDays(30));
    $muted = Space::factory()->inGroup($this->group)->create();
    NotificationSpaceLevel::query()->create(['user_id' => $this->plain->id, 'space_id' => $muted->id, 'level' => KnowiiSpaceNotificationLevel::None]);
    $read = Space::factory()->inGroup($this->group)->create();

    // New content in every space (a chat space and a link space never get a dot)
    foreach ([$this->talk, $this->announce, $this->chat, $this->link, $muted, $read] as $space) {
        $space->forceFill(['last_content_at' => now()->subHour()])->save();
    }
    // The member visited one space after its last content
    SpaceVisit::query()->create(['user_id' => $this->plain->id, 'space_id' => $read->id, 'visited_at' => now()]);

    $tree = fn (?User $user) => SpaceTree::for($this->community, $user)->flatMap(fn ($group) => $group->spaces)->mapWithKeys(fn (Space $space) => [$space->id => $space->toResource()->resolve(request())])->all();

    $this->actingAs($this->plain);
    $spaces = $tree($this->plain);
    expect($spaces[$this->talk->id]['has_new'])->toBeTrue()
        ->and($spaces[$this->talk->id]['notification_level'])->toBe('mine')
        ->and($spaces[$this->announce->id]['has_new'])->toBeTrue()
        ->and($spaces[$this->announce->id]['notification_level'])->toBe('all')
        ->and($spaces[$this->chat->id]['has_new'])->toBeFalse()
        ->and($spaces[$this->link->id]['has_new'])->toBeFalse()
        ->and($spaces[$this->link->id])->not->toHaveKey('notification_level')
        ->and($spaces[$muted->id]['has_new'])->toBeFalse()
        ->and($spaces[$muted->id]['notification_level'])->toBe('none')
        ->and($spaces[$read->id]['has_new'])->toBeFalse();

    // Content older than the day the member joined gives no dot
    wiaJoined($this->plain, now());
    $this->plain->unsetRelation('communities');
    expect($tree($this->plain->fresh())[$this->talk->id]['has_new'])->toBeFalse();

    // A visitor and a signed-in outsider get neither field
    foreach ([null, $this->outsider] as $reader) {
        $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
        $spaces = $tree($reader);
        expect($spaces[$public->id])->not->toHaveKey('has_new')->not->toHaveKey('notification_level');
    }
});

test('the dot goes when the member opens the space, and the web pages carry it', function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    wiaJoined($this->plain, now()->subDays(30));
    $this->talk->forceFill(['last_content_at' => now()->subHour()])->save();

    $this->actingAs($this->plain)->get("/community/{$this->community->slug}/space/{$this->announce->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spaceGroups.0.spaces.0.has_new', true)
            ->where('spaceGroups.0.spaces.0.cuid', $this->talk->cuid));

    $this->get("/community/{$this->community->slug}/space/{$this->talk->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('spaceGroups.0.spaces.0.has_new', false));
});

test('the navigation runs the same queries with 1 and with 9 spaces', function () {
    $count = function (): int {
        $user = User::query()->findOrFail($this->plain->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        SpaceTree::for($this->community, $user)->toResourceCollection()->resolve(request());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $this->talk->delete();
    $this->announce->delete();
    $this->chat->delete();
    $this->link->delete();
    $one = Space::factory()->inGroup($this->group)->create(['last_content_at' => now()]);
    $oneSpace = $count();

    foreach (range(1, 8) as $i) {
        $space = Space::factory()->inGroup($this->group)->create(['last_content_at' => now()]);
        if ($i % 3 === 0) {
            NotificationSpaceLevel::query()->create(['user_id' => $this->plain->id, 'space_id' => $space->id, 'level' => KnowiiSpaceNotificationLevel::All]);
        }
    }
    $nineSpaces = $count();

    expect($one)->not->toBeNull()
        ->and(Space::query()->where('community_id', $this->community->id)->count())->toBe(9)
        ->and($nineSpaces)->toBe($oneSpace);
});

test('the rows of visits go when the member leaves or is removed, and with the account and the space', function () {
    $other = Space::factory()->create();
    $otherCommunity = $other->community;
    foreach ([$this->plain, $this->other] as $member) {
        SpaceVisit::query()->create(['user_id' => $member->id, 'space_id' => $this->talk->id, 'visited_at' => now()]);
    }
    $otherCommunity->users()->attach($this->plain->id, ['role' => 'member']);
    SpaceVisit::query()->create(['user_id' => $this->plain->id, 'space_id' => $other->id, 'visited_at' => now()]);

    // Leaving
    $this->actingAs($this->plain)->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))->assertSuccessful();
    expect(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $this->talk->id)->exists())->toBeFalse()
        // Their visits elsewhere stay, and the other member's row stays
        ->and(SpaceVisit::query()->where('user_id', $this->plain->id)->where('space_id', $other->id)->exists())->toBeTrue()
        ->and(SpaceVisit::query()->where('user_id', $this->other->id)->exists())->toBeTrue();

    // Removal
    $this->community->removeUser($this->other);
    expect(SpaceVisit::query()->where('user_id', $this->other->id)->exists())->toBeFalse();

    // The cascade of the account and of the space
    SpaceVisit::query()->create(['user_id' => $this->other->id, 'space_id' => $this->talk->id, 'visited_at' => now()]);
    SpaceVisit::query()->create(['user_id' => $this->c['member']->id, 'space_id' => $this->announce->id, 'visited_at' => now()]);
    $this->talk->delete();
    expect(SpaceVisit::query()->where('space_id', $this->talk->id)->exists())->toBeFalse();
    DB::table('users')->where('id', $this->c['member']->id)->delete();
    expect(SpaceVisit::query()->where('user_id', $this->c['member']->id)->exists())->toBeFalse();
});

test('a member cannot hold two rows for one space', function () {
    SpaceVisit::query()->create(['user_id' => $this->plain->id, 'space_id' => $this->talk->id, 'visited_at' => now()]);

    expect(fn () => SpaceVisit::query()->create(['user_id' => $this->plain->id, 'space_id' => $this->talk->id, 'visited_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

// The own export

test('the own export holds the zone, the last visit date, the pop-up switch and the visits of spaces', function () {
    $this->plain->forceFill(['time_zone' => 'Europe/Lisbon', 'last_visit_on' => '2026-11-02', 'notification_popups' => false])->save();
    SpaceVisit::query()->create(['user_id' => $this->plain->id, 'space_id' => $this->talk->id, 'visited_at' => '2026-11-03 10:00:00']);
    Storage::fake('local');

    $zip = new ZipArchive;
    $path = app(ExportUser::class)->export($this->plain->fresh());
    expect($zip->open($path))->toBeTrue();
    $account = json_decode((string) $zip->getFromName('account.json'), true);
    $activity = json_decode((string) $zip->getFromName('activity.json'), true);
    $zip->close();

    expect($account['account']['time_zone'])->toBe('Europe/Lisbon')
        ->and($account['account']['last_visit_on'])->toBe('2026-11-02')
        ->and($account['account']['notification_popups'])->toBeFalse()
        ->and($activity['space_visits'])->toHaveCount(1)
        ->and($activity['space_visits'][0]['space'])->toBe($this->talk->cuid);
});

// 6. The tab title

test('the count answer holds the number of conversations with new messages, from the cursor of the conversations', function () {
    $post = function (User $from, array $to, string $text): string {
        app('auth')->forgetGuards();

        return test()->actingAs($from)->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATIONS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]), ['participant_cuids' => array_map(fn (User $user) => $user->cuid, $to), 'body' => $text])->assertCreated()->json('data.cuid');
    };
    $count = function (User $user): array {
        app('auth')->forgetGuards();

        return test()->actingAs($user)->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_COUNT))->assertOk()->json('data');
    };

    expect($count($this->plain))->toBe(['count' => 0, 'messages' => 0]);

    // Two conversations with several messages count as 2, not as the number of messages
    $first = $post($this->other, [$this->plain], 'One');
    $post($this->c['member'], [$this->plain], 'Two');
    $post($this->other, [$this->plain], 'Three');
    expect($count($this->plain)['messages'])->toBe(2)
        ->and(Conversation::query()->count())->toBe(2);

    // Reading one conversation moves the cursor: the number follows
    app('auth')->forgetGuards();
    $last = ChatMessage::query()->where('conversation_id', Conversation::query()->where('cuid', $first)->value('id'))->orderByDesc('id')->firstOrFail();
    $this->actingAs($this->plain)->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATION_READ, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_CONVERSATION => $first]), ['message_cuid' => $last->cuid])->assertSuccessful();
    expect($count($this->plain)['messages'])->toBe(1)
        // The author never has anything unread of their own
        ->and($count($this->c['member'])['messages'])->toBe(0);
});

test('the bell number does not count the messages', function () {
    $post = wiaPost($this->plain, $this->talk);
    wiaNeedsYou($this->plain, wiaComment($this->c['member'], $post));

    $this->actingAs($this->plain)->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_COUNT))->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.messages', 0);
});
