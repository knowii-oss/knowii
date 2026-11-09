<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Mention;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\Markdown;
use Illuminate\Support\Facades\Event;

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
    $this->c['admin']->update(['name' => 'Grace Hopper']);

    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]).'?query=hop')
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
