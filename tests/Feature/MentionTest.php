<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Event as EventModel;
use App\Models\Mention;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\Markdown;
use App\Support\MentionLinks;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
    $this->admin = $this->c['admin']->username;
    $this->outsider = $this->c['outsider']->username;
});

test('mentions of members become profile links; other @names stay text', function () {
    $rendered = Markdown::renderWithMentions("Hi @{$this->admin} and @{$this->outsider}, mail me at x@{$this->admin}.com", $this->c['community']);

    $profile = route('members.show', ['communitySlug' => $this->c['community']->slug, 'username' => $this->admin], false);
    expect($rendered->html)->toContain("<a class=\"mention\" href=\"{$profile}\">@{$this->admin}</a>")
        ->and($rendered->html)->toContain("@{$this->outsider}")
        ->and($rendered->html)->not->toContain("member/{$this->outsider}")
        ->and(collect($rendered->mentioned)->pluck('id')->all())->toBe([$this->c['admin']->id]);
});

test('mentions are case-insensitive, and the owner can be mentioned', function () {
    $rendered = Markdown::renderWithMentions('@'.strtoupper($this->admin).' @'.$this->c['owner']->username, $this->c['community']);

    expect(collect($rendered->mentioned)->pluck('id')->sort()->values()->all())->toBe(collect([$this->c['admin']->id, $this->c['owner']->id])->sort()->values()->all());
});

test('mentions in code are not links', function () {
    $html = Markdown::renderWithMentions("`@{$this->admin}`", $this->c['community'])->html;

    expect($html)->not->toContain('class="mention"');
});

test('posts and comments store their mentions, without the author, and keep them in step on edit', function () {
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, [
        'title' => 'Hello',
        'body' => "Thanks @{$this->admin} and @{$this->c['member']->username}!",
    ]);
    expect($post->mentions()->pluck('user_id')->all())->toBe([$this->c['admin']->id])
        ->and($post->body_html)->toContain('class="mention"');

    app(UpdatesPosts::class)->update($this->c['member'], $post, ['body' => "Thanks @{$this->c['moderator']->username}"]);
    expect($post->mentions()->pluck('user_id')->all())->toBe([$this->c['moderator']->id]);

    $comment = app(CreatesComments::class)->create($this->c['admin'], $post, ['body' => "@{$this->c['member']->username} you are welcome"]);
    expect($comment->mentions()->pluck('user_id')->all())->toBe([$this->c['member']->id])
        ->and(Mention::count())->toBe(2);

    $post->delete();
    expect(Mention::count())->toBe(0);
});

test('the member search finds members by username or name, never outsiders', function () {
    // A name that faker never produces: the other members have random names (#1313)
    $this->c['admin']->update(['name' => 'Grace Qxzhopper']);

    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]).'?query=qxzhop')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', $this->admin)
        ->assertJsonMissingPath('data.0.email');

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]).'?query='.substr($this->outsider, 0, 5))
        ->assertOk()
        ->assertJsonMissing(['username' => $this->outsider]);

    $this->actingAs($this->c['outsider'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]).'?query=a')
        ->assertForbidden();
});

test('the member page shows the public profile to members only', function () {
    $url = route('members.show', ['communitySlug' => $this->c['community']->slug, 'username' => $this->admin]);

    $this->actingAs($this->c['member'])->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Members/Show')
            ->where('member.username', $this->admin)
            ->missing('member.email'));

    $this->actingAs($this->c['outsider'])->get($url)->assertForbidden();
    $this->actingAs($this->c['member'])
        ->get(route('members.show', ['communitySlug' => $this->c['community']->slug, 'username' => $this->outsider]))
        ->assertNotFound();
});

test('the preview renders mentions for the members of the community', function () {
    $this->actingAs($this->c['member'])
        ->postJson(ApiRoutes::path(ApiRoutes::MARKDOWN_PREVIEW), ['markdown' => "@{$this->admin}", 'community_cuid' => $this->c['community']->cuid])
        ->assertOk()
        ->assertSee('class=\"mention\"', false);
});

/**
 * A public community with a public discussion space, a public pages space and a public events space. A member writes a
 * post and a comment that mention the admin, who never wrote anything, and a person who is not a member. The page and the
 * event hold a stored mention link, to test the rule of the reader on every rendered text.
 *
 * @return array{html: array<string, Closure(string): string>, link: string}
 */
function mentionReads(): array
{
    $test = test();
    $community = $test->c['community'];
    $community->update(['visibility' => KnowiiCommunityVisibility::Public]);
    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $make = fn (KnowiiSpaceType $type) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => KnowiiSpaceVisibility::Public]);
    $link = '<a class="mention" href="'.route('members.show', ['communitySlug' => $community->slug, 'username' => $test->admin], false).'">@'.$test->admin.'</a>';

    $discussion = $make(KnowiiSpaceType::Discussion);
    $post = app(CreatesPosts::class)->create($test->c['member'], $discussion, ['title' => 'Welcome', 'body' => "Welcome @{$test->admin} and @{$test->outsider}"]);
    $comment = app(CreatesComments::class)->create($test->c['member'], $post, ['body' => "Thanks @{$test->admin}"]);
    expect($post->body_html)->toContain($link)->and($comment->body_html)->toContain($link);

    $pages = $make(KnowiiSpaceType::Pages);
    $page = Page::factory()->inSpace($pages)->create(['author_id' => $test->c['owner']->id, 'body' => "Ask @{$test->admin}", 'body_html' => "<p>Ask {$link}</p>"]);
    $events = $make(KnowiiSpaceType::Events);
    $event = EventModel::factory()->inSpace($events)->hostedBy($test->c['owner'])->create([
        'body' => "With @{$test->admin}", 'body_html' => "<p>With {$link}</p>", 'notes' => "Notes of @{$test->admin}", 'notes_html' => "<p>Notes of {$link}</p>",
    ]);
    $eventComment = Comment::factory()->onEvent($event)->create(['author_id' => $test->c['member']->id, 'body' => "See @{$test->admin}", 'body_html' => "<p>See {$link}</p>"]);
    $chat = $make(KnowiiSpaceType::Chat);
    ChatMessage::factory()->inSpace($chat)->create(['author_id' => $test->c['member']->id, 'body' => "Hi @{$test->admin}", 'body_html' => "<p>Hi {$link}</p>"]);

    $slugs = fn (Space $space) => ['communitySlug' => $community->slug, 'spaceSlug' => $space->slug];
    $api = fn (string $route, Space $space, array $more = []) => ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid, ...$more]);
    $props = function (string $reader, string $url): array {
        /** @var array{props: array<string, mixed>} $page */
        $page = mentionReader($reader)->get($url)->assertOk()->viewData('page');

        return $page['props'];
    };
    $json = fn (string $reader, string $url, string $key) => (string) json_encode(mentionReader($reader)->getJson($url)->assertOk()->json($key));

    return [
        'link' => $link,
        'html' => [
            'the post page: the post' => fn (string $reader) => $props($reader, route('posts.show', [...$slugs($discussion), 'postSlug' => $post->slug]))['post']['body_html'],
            'the post page: the comment' => fn (string $reader) => $props($reader, route('posts.show', [...$slugs($discussion), 'postSlug' => $post->slug]))['comments'][0]['body_html'],
            'the space page: the list of posts' => fn (string $reader) => (string) json_encode($props($reader, route('spaces.show', $slugs($discussion)))['posts']),
            'the page of a pages space' => fn (string $reader) => $props($reader, route('pages.show', [...$slugs($pages), 'pageSlug' => $page->slug]))['page']['body_html'],
            'the event page: the description' => fn (string $reader) => $props($reader, route('events.show', [...$slugs($events), 'eventSlug' => $event->slug]))['event']['body_html'],
            'the event page: the notes' => fn (string $reader) => $props($reader, route('events.show', [...$slugs($events), 'eventSlug' => $event->slug]))['event']['notes_html'],
            'the event page: the comment' => fn (string $reader) => $props($reader, route('events.show', [...$slugs($events), 'eventSlug' => $event->slug]))['comments'][0]['body_html'],
            'the API: the post' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_POST, $discussion, [ApiRoutes::PARAM_POST => $post->cuid]), 'data.body_html'),
            'the API: the list of posts' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_POSTS, $discussion), 'data'),
            'the API: the comments of the post' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $discussion, [ApiRoutes::PARAM_POST => $post->cuid]), 'data'),
            'the API: the page' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_PAGE, $pages, [ApiRoutes::PARAM_PAGE => $page->cuid]), 'data'),
            'the API: the event' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_EVENT, $events, [ApiRoutes::PARAM_EVENT => $event->cuid]), 'data'),
            'the API: the comments of the event' => fn (string $reader) => $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENTS, $events, [ApiRoutes::PARAM_EVENT => $event->cuid]), 'data'),
            'the API: the newest posts of the public page' => fn (string $reader) => $json($reader, ApiRoutes::path(ApiRoutes::COMMUNITY_NEWEST_POSTS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), 'data'),
            // A chat space is for signed-in people: a visitor never reads it
            'the API: the messages of a public chat space' => fn (string $reader) => $reader === 'visitor' ? '@'.$test->admin : $json($reader, $api(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $chat), 'data'),
        ],
    ];
}

/**
 * The test as a reader: a visitor (nobody signed in), or one of the people of the community
 *
 * @return TestCase
 */
function mentionReader(string $reader): mixed
{
    app('auth')->forgetGuards();

    return $reader === 'visitor' ? test() : test()->actingAs(User::query()->findOrFail(test()->c[$reader]->id));
}

test('for a reader who is not a member, a mention is plain text', function (string $reader) {
    $reads = mentionReads();

    foreach ($reads['html'] as $where => $read) {
        $html = stripslashes($read($reader));

        // The text of the mention stays, as a typed @word: no link to a member page, no class
        expect(str_contains($html, '@'.$this->admin))->toBeTrue("{$where}: the text of the mention is missing")
            ->and(str_contains($html, 'class="mention"'))->toBeFalse("{$where}: the class of a mention reaches the reader")
            ->and(str_contains($html, '/member/'))->toBeFalse("{$where}: the address of a member page reaches the reader")
            ->and(str_contains($html, '<a '))->toBeFalse("{$where}: a link reaches the reader");
    }
})->with(['visitor', 'outsider']);

test('for a member of the community, a mention is a link to the member page', function (string $reader) {
    $reads = mentionReads();

    foreach ($reads['html'] as $where => $read) {
        $html = stripslashes($read($reader));

        if ($where === 'the API: the newest posts of the public page') {
            // An excerpt is plain text for every reader
            expect(str_contains($html, '@'.$this->admin))->toBeTrue($where)
                ->and(str_contains($html, 'class="mention"'))->toBeFalse($where);

            continue;
        }

        expect(str_contains($html, $reads['link']))->toBeTrue("{$where}: the link of the mention is missing");
    }
})->with(['member', 'admin', 'owner']);

test('a mention of a member and a typed @word look the same to a reader who is not a member', function () {
    $html = MentionLinks::asText('<p>Hi <a class="mention" href="/community/c/member/ada">@ada</a>, @nobody and <a href="https://example.com">a link</a></p>');

    expect($html)->toBe('<p>Hi @ada, @nobody and <a href="https://example.com">a link</a></p>');
});
