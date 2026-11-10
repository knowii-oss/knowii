<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use Laravel\Sanctum\Sanctum;

/*
 * Course progress per member (#1304, ADR 0034).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->course = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Course, 'name' => 'Starter Course']);
    $position = 0;
    $this->section = Page::factory()->inSpace($this->course)->create(['kind' => KnowiiPageKind::Section, 'title' => 'Basics', 'body' => '', 'body_html' => '', 'position' => $position++]);
    $this->lessons = collect(['One', 'Two', 'Three'])
        ->map(fn (string $title) => Page::factory()->inSpace($this->course)->create(['title' => $title, 'position' => $position++]))
        ->all();
});

function progressPath(Space $space, ?Page $page = null): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];

    return $page === null
        ? ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PROGRESS, $params)
        : ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGE_PROGRESS, [...$params, ApiRoutes::PARAM_PAGE => $page->cuid]);
}

test('a member marks lessons as done and gets the progress of the course (#1304)', function () {
    [$one, $two, $three] = $this->lessons;
    $this->actingAs($this->c['member']);

    $this->getJson(progressPath($this->course))
        ->assertOk()
        ->assertExactJson([
            'category' => 'business', 'type' => KnowiiApiResponseType::Success->value, 'message' => 'Course progress',
            'data' => ['total' => 3, 'completed' => 0, 'completed_lessons' => [], 'next_lesson' => $one->cuid],
        ]);

    $this->putJson(progressPath($this->course, $one))
        ->assertOk()
        ->assertJsonPath('data.completed', 1)
        ->assertJsonPath('data.completed_lessons', [$one->cuid])
        ->assertJsonPath('data.next_lesson', $two->cuid);

    // Again: nothing changes
    $this->putJson(progressPath($this->course, $one))->assertOk()->assertJsonPath('data.completed', 1);
    expect(LessonProgress::query()->count())->toBe(1);

    // Where to continue: the first lesson that is not done, also after a later one
    $this->putJson(progressPath($this->course, $three))->assertJsonPath('data.next_lesson', $two->cuid);
    $this->putJson(progressPath($this->course, $two))->assertJsonPath('data.completed', 3)->assertJsonPath('data.next_lesson', null);

    $this->deleteJson(progressPath($this->course, $two))
        ->assertOk()
        ->assertJsonPath('data.completed', 2)
        ->assertJsonPath('data.next_lesson', $two->cuid);
    $this->deleteJson(progressPath($this->course, $two))->assertOk()->assertJsonPath('data.completed', 2);
});

test('the progress is private to each member (#1304)', function () {
    $this->actingAs($this->c['member'])->putJson(progressPath($this->course, $this->lessons[0]))->assertOk();

    $this->actingAs($this->c['admin'])->getJson(progressPath($this->course))->assertJsonPath('data.completed', 0);
});

test('sections and spaces that are not courses have no progress (#1304)', function () {
    $this->actingAs($this->c['member'])->putJson(progressPath($this->course, $this->section))->assertStatus(422);

    $pages = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Pages]);
    $page = Page::factory()->inSpace($pages)->create();
    $this->actingAs($this->c['member'])->putJson(progressPath($pages, $page))->assertStatus(422);
    $this->actingAs($this->c['member'])->getJson(progressPath($pages))->assertStatus(422);
});

test('outsiders keep no progress, also in a public course (#1304)', function () {
    $this->actingAs($this->c['outsider'])->putJson(progressPath($this->course, $this->lessons[0]))->assertForbidden();

    $this->course->update(['visibility' => KnowiiSpaceVisibility::Public]);
    $this->actingAs($this->c['outsider'])->putJson(progressPath($this->course, $this->lessons[0]))->assertForbidden();
    $this->actingAs($this->c['outsider'])->getJson(progressPath($this->course))->assertForbidden();
    expect(LessonProgress::query()->count())->toBe(0);
});

test('a member outside the groups of a groups course keeps no progress in it (#1304)', function () {
    $group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $this->course->update(['visibility' => KnowiiSpaceVisibility::Groups]);
    $this->course->accessGroups()->attach($group);

    $this->actingAs($this->c['member'])->putJson(progressPath($this->course, $this->lessons[0]))->assertForbidden();

    $group->members()->attach($this->c['member']);
    // A fresh user: the first request loaded its access groups
    $this->actingAs($this->c['member']->fresh())->putJson(progressPath($this->course, $this->lessons[0]))->assertOk();
});

test('a lesson of another space or community is not found (#1304)', function () {
    $other = communityWithMembers();
    $otherCourse = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $other['community']->id]))->create(['type' => KnowiiSpaceType::Course]);
    $foreign = Page::factory()->inSpace($otherCourse)->create();

    $params = [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->course->cuid, ApiRoutes::PARAM_PAGE => $foreign->cuid];
    $this->actingAs($this->c['member'])->putJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGE_PROGRESS, $params))->assertNotFound();
});

test('the progress needs pages:read and a verified address (#1304)', function () {
    Sanctum::actingAs($this->c['member'], ['posts:read']);
    $this->putJson(progressPath($this->course, $this->lessons[0]))->assertForbidden();

    Sanctum::actingAs($this->c['member'], ['pages:read']);
    $this->putJson(progressPath($this->course, $this->lessons[0]))->assertOk();

    $this->c['member']->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->c['member'])->getJson(progressPath($this->course))->assertForbidden();
});

test('the progress goes when the member leaves, when the lesson goes, and with the account (#1304)', function () {
    [$one, $two] = $this->lessons;
    foreach (['member', 'moderator'] as $who) {
        $this->actingAs($this->c[$who])->putJson(progressPath($this->course, $one))->assertOk();
        $this->actingAs($this->c[$who])->putJson(progressPath($this->course, $two))->assertOk();
    }

    $this->c['community']->removeUser($this->c['member']);
    expect(LessonProgress::query()->where('user_id', $this->c['member']->id)->count())->toBe(0);

    $one->delete();
    expect(LessonProgress::query()->where('user_id', $this->c['moderator']->id)->count())->toBe(1);

    $this->c['moderator']->delete();
    expect(LessonProgress::query()->count())->toBe(0);
});

test('the course page and the lesson page carry the progress of the member (#1304)', function () {
    [$one, $two] = $this->lessons;
    $this->actingAs($this->c['member'])->putJson(progressPath($this->course, $one))->assertOk();
    $community = $this->c['community'];

    $this->actingAs($this->c['member'])
        ->get(route('spaces.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug]))
        ->assertInertia(fn ($page) => $page->where('progress.completed', 1)->where('progress.next_lesson', $two->cuid));

    $this->actingAs($this->c['member'])
        ->get(route('pages.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $one->slug]))
        ->assertInertia(fn ($page) => $page->where('progress.done', true)->where('progress.course.total', 3));

    $this->actingAs($this->c['member'])
        ->get(route('pages.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $two->slug]))
        ->assertInertia(fn ($page) => $page->where('progress.done', false));

    // A signed-in outsider reads a public course without a progress
    $this->course->update(['visibility' => KnowiiSpaceVisibility::Public]);
    $this->actingAs($this->c['outsider'])
        ->get(route('spaces.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug]))
        ->assertInertia(fn ($page) => $page->where('progress', null));
});
