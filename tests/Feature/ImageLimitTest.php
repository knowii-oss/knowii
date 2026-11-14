<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Rules\MaxMarkdownImages;

/*
 * A post, a comment, a message or a page holds a limited number of images (#1207).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
});

function markdownWithImages(int $count): string
{
    return "Our trip:\n".implode("\n", array_map(static fn (int $n): string => "![Image $n](/storage/uploads/abc/2026/11/img$n.webp)", range(1, $count)));
}

function imageLimitPath(string $route, Space $space, array $more = []): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid, ...$more]);
}

test('the image count reads the Markdown images of a text (#1207)', function () {
    expect(MaxMarkdownImages::count(''))->toBe(0)
        ->and(MaxMarkdownImages::count('A [link](https://example.com) is not an image'))->toBe(0)
        ->and(MaxMarkdownImages::count('![One](/a.webp) text ![Two](https://example.com/b.png "Title")'))->toBe(2)
        ->and(MaxMarkdownImages::count(markdownWithImages(21)))->toBe(21)
        // An image in a link, brackets in the alt text and raw HTML: the count is what the text shows
        ->and(MaxMarkdownImages::count('[![Badge](/badge.webp)](https://example.com) ![A [nested] alt](/b.webp) <img src="/c.webp">'))->toBe(2)
        // A long text of image starts stays fast
        ->and(MaxMarkdownImages::count(str_repeat('![', 20000)))->toBe(0);
});

test('a post takes 20 images and refuses 21 (#1207)', function () {
    $space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);
    $path = imageLimitPath(ApiRoutes::COMMUNITY_SPACE_POSTS, $space);
    $this->actingAs($this->c['member']);

    $this->postJson($path, ['title' => 'Too many images', 'body' => markdownWithImages(Constants::MAX_IMAGES_PER_CONTENT + 1)])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonPath('errors.body.0', 'Use at most 20 images.');

    $cuid = $this->postJson($path, ['title' => 'A full gallery', 'body' => markdownWithImages(Constants::MAX_IMAGES_PER_CONTENT)])
        ->assertCreated()
        ->json('data.cuid');

    // An edit follows the same rule
    $this->patchJson(imageLimitPath(ApiRoutes::COMMUNITY_SPACE_POST, $space, [ApiRoutes::PARAM_POST => $cuid]), ['body' => markdownWithImages(21)])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['body']]);
});

test('comments, chat messages and pages follow the same limit (#1207)', function () {
    $discussion = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);
    $post = Post::factory()->inSpace($discussion)->create(['author_id' => $this->c['member']->id]);
    $chat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    $pages = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Pages]);
    $tooMany = markdownWithImages(Constants::MAX_IMAGES_PER_CONTENT + 1);

    $this->actingAs($this->c['member'])
        ->postJson(imageLimitPath(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $discussion, [ApiRoutes::PARAM_POST => $post->cuid]), ['body' => $tooMany])
        ->assertStatus(400)->assertJsonStructure(['errors' => ['body']]);
    $this->actingAs($this->c['member'])
        ->postJson(imageLimitPath(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $chat), ['body' => $tooMany])
        ->assertStatus(400)->assertJsonStructure(['errors' => ['body']]);
    $this->actingAs($this->c['admin'])
        ->postJson(imageLimitPath(ApiRoutes::COMMUNITY_SPACE_PAGES, $pages), ['title' => 'Photo album', 'body' => $tooMany])
        ->assertStatus(400)->assertJsonStructure(['errors' => ['body']]);

    // The limit itself passes
    $this->actingAs($this->c['member'])
        ->postJson(imageLimitPath(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $discussion, [ApiRoutes::PARAM_POST => $post->cuid]), ['body' => markdownWithImages(Constants::MAX_IMAGES_PER_CONTENT)])
        ->assertCreated();
});
