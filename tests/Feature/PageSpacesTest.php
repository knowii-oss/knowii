<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Pages, 'name' => 'Start here']);
});

function pagesPath(Space $space, ?Page $page = null, string $suffix = ''): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];
    if ($page !== null) {
        return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGE, [...$params, ApiRoutes::PARAM_PAGE => $page->cuid]);
    }

    return ApiRoutes::path($suffix === 'order' ? ApiRoutes::COMMUNITY_SPACE_PAGES_ORDER : ApiRoutes::COMMUNITY_SPACE_PAGES, $params);
}

test('an admin builds the Start Here space with three ordered pages, and members read them in order (#1179)', function () {
    Event::fake([SpaceContentChanged::class]);
    foreach (['Welcome', 'House rules', 'Introduce yourself'] as $title) {
        $this->actingAs($this->c['admin'])->postJson(pagesPath($this->space), ['title' => $title, 'body' => "# {$title}\n\nText"])
            ->assertCreated()
            ->assertJsonPath('data.title', $title);
    }
    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::PAGE_CREATED);

    $list = $this->actingAs($this->c['member'])->getJson(pagesPath($this->space))->assertOk();
    expect($list->json('data.*.title'))->toBe(['Welcome', 'House rules', 'Introduce yourself'])
        ->and($list->json('data.0.body'))->toBeNull();

    // The page view links the previous and the next page
    $this->actingAs($this->c['member'])->get(route('pages.show', [
        'communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'pageSlug' => 'house-rules',
    ]))->assertOk()->assertInertia(fn ($page) => $page->component('SpacePages/Show')
        ->where('page.title', 'House rules')
        ->where('page.body_html', fn (string $html) => str_contains($html, '<h1>House rules</h1>'))
        ->where('previous.slug', 'welcome')
        ->where('next.slug', 'introduce-yourself')
        ->where('position', ['current' => 2, 'total' => 3])
        ->where('permissions.canManagePages', false));

    // The space page lists them in order
    $this->actingAs($this->c['member'])->get(route('spaces.show', [
        'communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug,
    ]))->assertInertia(fn ($page) => $page->where('pages.0.title', 'Welcome')->where('pages.2.title', 'Introduce yourself'));
});

test('the owner, the admins and the moderators manage pages; members and outsiders do not (#1179)', function (string $who, int $status) {
    $this->actingAs($this->c[$who])->postJson(pagesPath($this->space), ['title' => 'A page', 'body' => 'Text'])->assertStatus($status);
})->with([
    'owner' => ['owner', 201],
    'admin' => ['admin', 201],
    'moderator' => ['moderator', 201],
    'member' => ['member', 403],
    // The members space answers an outsider as a space that does not exist
    'outsider' => ['outsider', 404],
]);

test('pages are edited, reordered and deleted (#1179)', function () {
    $first = Page::factory()->inSpace($this->space)->create(['title' => 'First', 'position' => 0]);
    $second = Page::factory()->inSpace($this->space)->create(['title' => 'Second', 'position' => 1]);

    $this->actingAs($this->c['moderator'])->patchJson(pagesPath($this->space, $first), ['title' => 'First, edited', 'body' => '**Bold**'])
        ->assertOk()
        ->assertJsonPath('data.title', 'First, edited')
        ->assertJsonPath('data.slug', $first->slug)
        ->assertJsonPath('data.body_html', '<p><strong>Bold</strong></p>');

    $this->actingAs($this->c['moderator'])->postJson(pagesPath($this->space, suffix: 'order'), ['pages' => [$second->cuid, $first->cuid]])
        ->assertOk()
        ->assertJsonPath('data.0.cuid', $second->cuid);

    $this->actingAs($this->c['moderator'])->deleteJson(pagesPath($this->space, $second))->assertOk();
    expect(Page::query()->whereKey($second->id)->exists())->toBeFalse();
});

test('a new order must list every page of the space once', function () {
    $first = Page::factory()->inSpace($this->space)->create(['position' => 0]);
    Page::factory()->inSpace($this->space)->create(['position' => 1]);

    $this->actingAs($this->c['admin'])->postJson(pagesPath($this->space, suffix: 'order'), ['pages' => [$first->cuid]])
        ->assertStatus(400)
        ->assertJsonPath('type', 'validationIssue');
});

test('the members who cannot read a space never read its pages (access groups, #1241)', function () {
    $this->space->update(['visibility' => KnowiiSpaceVisibility::Groups]);
    $page = Page::factory()->inSpace($this->space)->create();

    $this->actingAs($this->c['member'])->getJson(pagesPath($this->space))->assertForbidden();
    $this->actingAs($this->c['member'])->getJson(pagesPath($this->space, $page))->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(pagesPath($this->space), ['title' => 'A page', 'body' => 'Text'])->assertForbidden();
    $this->actingAs($this->c['outsider'])->get(route('pages.show', [
        'communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'pageSlug' => $page->slug,
    ]))->assertForbidden();
});

test('pages exist only in page spaces', function () {
    $discussion = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Discussion]);

    $this->actingAs($this->c['owner'])->postJson(pagesPath($discussion), ['title' => 'A page', 'body' => 'Text'])->assertStatus(422);
    $this->actingAs($this->c['owner'])->getJson(pagesPath($discussion))->assertStatus(422);
});

test('the raw HTML of a page is escaped (ADR 0013)', function () {
    $this->actingAs($this->c['admin'])->postJson(pagesPath($this->space), ['title' => 'A page', 'body' => 'Hi <script>alert(1)</script>'])
        ->assertCreated()
        ->assertJsonPath('data.body_html', '<p>Hi &lt;script&gt;alert(1)&lt;/script&gt;</p>');
});

test('an image used only by a page survives uploads:prune (#1201)', function () {
    $upload = app(UploadsImages::class)->upload($this->c['admin'], $this->c['community'], [
        'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        'alt' => 'A photo',
    ]);
    $this->actingAs($this->c['admin'])->postJson(pagesPath($this->space), ['title' => 'With a photo', 'body' => "![A photo]({$upload->url()})"])->assertCreated();
    Upload::query()->whereKey($upload->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeTrue();
});

test('the token abilities guard the page routes (ADR 0022)', function () {
    $token = $this->c['admin']->createToken('read only', ['pages:read'])->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson(pagesPath($this->space))->assertOk();
    $this->withToken($token)->postJson(pagesPath($this->space), ['title' => 'A page', 'body' => 'Text'])->assertForbidden();
});

test('the list of the pages is paged, with a largest offset, and reads no text of a page', function () {
    $pages = Page::factory()->count(5)->sequence(fn ($sequence) => ['position' => $sequence->index, 'title' => 'Page '.($sequence->index + 1)])
        ->create(['community_id' => $this->c['community']->id, 'space_id' => $this->space->id, 'author_id' => $this->c['admin']->id]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'from "pages"')) {
            $queries[] = $query->sql;
        }
    });

    $list = $this->actingAs($this->c['member'])->getJson(pagesPath($this->space).'?limit=2&offset=1')->assertOk();

    expect($list->json('data.*.title'))->toBe(['Page 2', 'Page 3'])
        ->and($list->json('metadata.pagination.totalCount'))->toBe(5)
        ->and($list->json('metadata.pagination.nextOffset'))->toBe(3)
        ->and(implode("\n", $queries))->not->toContain('"body"')->not->toContain('"body_html"')->not->toContain('"pages".*')->not->toContain('select * from "pages"');
    // Without a limit: every page of the space, which holds at most MAX_PAGES_PER_SPACE
    expect($this->getJson(pagesPath($this->space))->json('data'))->toHaveCount(5)
        // An offset after the largest one is the largest one
        ->and($this->getJson(pagesPath($this->space).'?offset=999999999')->json('metadata.pagination.offset'))->toBe(Constants::MAX_PAGES_PER_SPACE);
    expect($pages)->toHaveCount(5);
});

test('a space holds at most 500 pages', function () {
    Page::factory()->count(Constants::MAX_PAGES_PER_SPACE)->sequence(fn ($sequence) => ['position' => $sequence->index])
        ->create(['community_id' => $this->c['community']->id, 'space_id' => $this->space->id, 'author_id' => $this->c['admin']->id]);

    $this->actingAs($this->c['admin'])->postJson(pagesPath($this->space), ['title' => 'One more', 'body' => 'Text'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A space can have at most 500 pages');
});
