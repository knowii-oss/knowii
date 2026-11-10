<?php

use App\ApiRoutes;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\VideoEmbed;
use Illuminate\Database\QueryException;

/*
 * Course spaces (#1188, ADR 0029): lessons are pages with an optional video, sections are headings in the same list.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->course = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Course, 'name' => 'Obsidian Starter Course']);
});

function lessonsPath(Space $space, ?Page $page = null): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];

    return $page === null
        ? ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGES, $params)
        : ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGE, [...$params, ApiRoutes::PARAM_PAGE => $page->cuid]);
}

/**
 * Two sections with two lessons each, all with a video
 */
function buildCourse(Space $course): void
{
    test()->actingAs(test()->c['admin']);
    foreach (['Getting started' => ['Install Obsidian', 'Your first note'], 'Linking' => ['Links', 'Backlinks']] as $section => $lessons) {
        test()->postJson(lessonsPath($course), ['kind' => 'section', 'title' => $section])->assertCreated()->assertJsonPath('data.kind', 'section');
        foreach ($lessons as $index => $lesson) {
            test()->postJson(lessonsPath($course), [
                'title' => $lesson,
                'body' => "About {$lesson}",
                'video_url' => $index === 0 ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : 'https://vimeo.com/76979871',
            ])->assertCreated();
        }
    }
}

test('the video addresses of YouTube and Vimeo become privacy-friendly players; anything else is refused', function (string $url, ?string $embed) {
    expect(VideoEmbed::parse($url)['embed_url'] ?? null)->toBe($embed);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'],
    'vimeo player' => ['https://player.vimeo.com/video/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'],
    'other host' => ['https://www.dailymotion.com/video/x7tgad0', null],
    'look-alike host' => ['https://youtube.com.example.org/watch?v=dQw4w9WgXcQ', null],
    'bad id' => ['https://www.youtube.com/watch?v=<script>', null],
    'script' => ['javascript:alert(1)', null],
]);

test('an admin builds a course with two sections and four lessons with videos', function () {
    buildCourse($this->course);

    $list = $this->actingAs($this->c['member'])->getJson(lessonsPath($this->course))->assertOk();

    expect($list->json('data.*.title'))->toBe(['Getting started', 'Install Obsidian', 'Your first note', 'Linking', 'Links', 'Backlinks'])
        ->and($list->json('data.*.kind'))->toBe(['section', 'page', 'page', 'section', 'page', 'page'])
        ->and($list->json('data.1.video'))->toMatchArray(['provider' => 'youtube', 'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'])
        ->and($list->json('data.2.video.provider'))->toBe('vimeo')
        ->and($list->json('data.0.video'))->toBeNull();
});

test('videos belong to lessons of course spaces only, from YouTube or Vimeo', function () {
    $this->actingAs($this->c['admin']);

    $this->postJson(lessonsPath($this->course), ['title' => 'A lesson', 'body' => 'Text', 'video_url' => 'https://example.com/video.mp4'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['video_url']]);
    $this->postJson(lessonsPath($this->course), ['kind' => 'section', 'title' => 'A section', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertStatus(400);

    $pages = Space::factory()->inGroup($this->course->spaceGroup)->create(['type' => KnowiiSpaceType::Pages]);
    $this->postJson(lessonsPath($pages), ['title' => 'A page', 'body' => 'Text', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['video_url']]);

    // A lesson changes or loses its video
    $lesson = Page::query()->where('space_id', $this->course->id)->first()
        ?? Page::factory()->create(['space_id' => $this->course->id, 'community_id' => $this->course->community_id]);
    $this->patchJson(lessonsPath($this->course, $lesson), ['video_url' => 'https://vimeo.com/76979871'])->assertOk()->assertJsonPath('data.video.provider', 'vimeo');
    $this->patchJson(lessonsPath($this->course, $lesson), ['video_url' => ''])->assertOk()->assertJsonPath('data.video', null);
});

test('a section is a title only, and the database refuses a section with a video', function () {
    $this->actingAs($this->c['admin'])
        ->postJson(lessonsPath($this->course), ['kind' => 'section', 'title' => 'Part one', 'body' => 'Not allowed'])
        ->assertStatus(400);

    expect(fn () => Page::factory()->create([
        'space_id' => $this->course->id,
        'community_id' => $this->course->community_id,
        'kind' => KnowiiPageKind::Section,
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]))->toThrow(QueryException::class);
});

test('members cannot change a course', function () {
    $this->actingAs($this->c['member'])->postJson(lessonsPath($this->course), ['title' => 'Mine', 'body' => 'Text'])->assertForbidden();
});

test('a member follows the course in order: the navigation skips the sections and names the section of the lesson', function () {
    buildCourse($this->course);
    $url = fn (string $slug) => route('pages.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $slug]);

    $this->actingAs($this->c['member'])->get($url('your-first-note'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('SpacePages/Show')
            ->where('page.video.provider', 'vimeo')
            ->where('section', 'Getting started')
            ->where('previous.slug', 'install-obsidian')
            // The next lesson, not the "Linking" section between them
            ->where('next.slug', 'links')
            ->where('position', ['current' => 2, 'total' => 4]));

    // A section has no page of its own
    $this->actingAs($this->c['member'])->get($url('linking'))->assertNotFound();

    $this->actingAs($this->c['member'])
        ->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->course->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pages', 6));
});

test('the Content Security Policy allows the two video players and nothing wider', function () {
    $header = (string) $this->get('/')->headers->get('Content-Security-Policy');
    preg_match('/frame-src ([^;]+)/', $header, $match);
    $sources = explode(' ', trim($match[1] ?? ''));

    expect($sources)->toContain('https://www.youtube-nocookie.com', 'https://player.vimeo.com')
        ->and($sources)->not->toContain('https:')
        ->and($sources)->not->toContain('https://www.youtube.com');
});
