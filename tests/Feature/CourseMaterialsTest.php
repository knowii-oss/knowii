<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Pages\DeletesPages;
use App\Contracts\Spaces\DeletesSpaces;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCourseMaterialKind;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\CourseMaterial;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * Supplementary materials of course spaces (#1336, ADR 0037).
 */

beforeEach(function () {
    Storage::fake('local');
    $this->c = communityWithMembers();
    $this->spaceGroup = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->course = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Course, 'name' => 'Starter Course']);
    $this->section = Page::factory()->inSpace($this->course)->create(['kind' => KnowiiPageKind::Section, 'title' => 'Basics', 'body' => '', 'body_html' => '', 'position' => 0]);
    $this->lesson = Page::factory()->inSpace($this->course)->create(['title' => 'First lesson', 'position' => 1]);
});

function materialsPath(Space $space, ?CourseMaterial $material = null, string $suffix = ''): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];

    return $material === null
        ? ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MATERIALS, $params).$suffix
        : ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MATERIAL, [...$params, ApiRoutes::PARAM_MATERIAL => $material->cuid]).$suffix;
}

/**
 * A PDF with a real PDF header: the type is read from the content.
 */
function materialPdf(string $name = 'Cheat sheet.pdf', int $kilobytes = 20): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('0', $kilobytes * 1024));
}

function addMaterialFile(Space $space, ?Page $page = null, ?UploadedFile $file = null, string $title = 'Cheat sheet'): TestResponse
{
    return test()->post(materialsPath($space), array_filter([
        'kind' => 'file',
        'title' => $title,
        'page' => $page?->cuid,
        'file' => $file ?? materialPdf(),
    ]), ['Accept' => 'application/json']);
}

function linkMaterial(Space $space, ?Page $page = null, string $title = 'Template'): CourseMaterial
{
    return CourseMaterial::query()->create([
        'community_id' => $space->community_id,
        'space_id' => $space->id,
        'page_id' => $page?->id,
        'title' => $title,
        'kind' => KnowiiCourseMaterialKind::Link,
        'url' => 'https://example.com/'.strtolower($title),
        'position' => (int) CourseMaterial::query()->where('space_id', $space->id)->where('page_id', $page?->id)->count(),
    ]);
}

// ---------------------------------------------------------------------------------------------------------------------
// Add
// ---------------------------------------------------------------------------------------------------------------------

test('a manager adds a link to the course, and a file to a lesson (#1336)', function () {
    $this->actingAs($this->c['moderator'])
        ->postJson(materialsPath($this->course), ['kind' => 'link', 'title' => ' Template vault ', 'url' => 'https://example.com/template'])
        ->assertCreated()
        ->assertJsonPath('type', KnowiiApiResponseType::Success->value)
        ->assertJsonPath('data.kind', 'link')
        ->assertJsonPath('data.title', 'Template vault')
        ->assertJsonPath('data.url', 'https://example.com/template')
        ->assertJsonPath('data.page_cuid', null)
        ->assertJsonPath('data.position', 0)
        ->assertJsonPath('data.file_name', null);

    $this->actingAs($this->c['admin']);
    $response = addMaterialFile($this->course, $this->lesson)
        ->assertCreated()
        ->assertJsonPath('data.kind', 'file')
        ->assertJsonPath('data.page_cuid', $this->lesson->cuid)
        ->assertJsonPath('data.file_name', 'Cheat sheet.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.url', null)
        // The place of the file never leaves the server
        ->assertJsonMissingPath('data.path')
        ->assertJsonMissingPath('data.disk');

    $material = CourseMaterial::query()->where('cuid', $response->json('data.cuid'))->firstOrFail();
    expect($material->disk)->toBe('local');
    expect($material->path)->toBe('course-materials/'.$this->c['community']->cuid.'/'.$material->cuid.'/cheat-sheet.pdf');
    expect($material->size_bytes)->toBeGreaterThan(20 * 1024);
    Storage::disk('local')->assertExists($material->path);
});

test('only the managers of the course add materials (#1336)', function (string $who, int $status) {
    $this->actingAs($this->c[$who])
        ->postJson(materialsPath($this->course), ['kind' => 'link', 'title' => 'Template', 'url' => 'https://example.com/template'])
        ->assertStatus($status);
})->with([
    'owner' => ['owner', 201],
    'admin' => ['admin', 201],
    'moderator' => ['moderator', 201],
    'member' => ['member', 403],
    'outsider' => ['outsider', 403],
]);

test('a moderator outside the groups of a groups course does not manage its materials (#1336, #1325)', function () {
    $group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid members']);
    $this->course->update(['visibility' => KnowiiSpaceVisibility::Groups]);
    $this->course->accessGroups()->attach($group);
    $material = linkMaterial($this->course);

    $body = ['kind' => 'link', 'title' => 'Template', 'url' => 'https://example.com/template'];
    $this->actingAs($this->c['moderator'])->postJson(materialsPath($this->course), $body)->assertForbidden();
    $this->actingAs($this->c['moderator'])->getJson(materialsPath($this->course))->assertForbidden();
    $this->actingAs($this->c['moderator'])->deleteJson(materialsPath($this->course, $material))->assertForbidden();
    $this->actingAs($this->c['member'])->getJson(materialsPath($this->course))->assertForbidden();

    $group->members()->attach($this->c['member']);
    $this->actingAs($this->c['member']->fresh())->getJson(materialsPath($this->course))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->c['admin'])->postJson(materialsPath($this->course), $body)->assertCreated();
});

test('a material needs a valid kind, title, address or file (#1336)', function (array $body, string $field) {
    $this->actingAs($this->c['owner'])
        ->postJson(materialsPath($this->course), $body)
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonStructure(['errors' => [$field]]);

    expect(CourseMaterial::query()->count())->toBe(0);
})->with([
    'no kind' => [['title' => 'Template', 'url' => 'https://example.com'], 'kind'],
    'unknown kind' => [['kind' => 'video', 'title' => 'Template'], 'kind'],
    'no title' => [['kind' => 'link', 'url' => 'https://example.com'], 'title'],
    'long title' => [['kind' => 'link', 'title' => str_repeat('a', Constants::MAX_LENGTH_COURSE_MATERIAL_TITLE + 1), 'url' => 'https://example.com'], 'title'],
    'no address' => [['kind' => 'link', 'title' => 'Template'], 'url'],
    'not http' => [['kind' => 'link', 'title' => 'Template', 'url' => 'javascript:alert(1)'], 'url'],
    'ftp' => [['kind' => 'link', 'title' => 'Template', 'url' => 'ftp://example.com/file'], 'url'],
    'no file' => [['kind' => 'file', 'title' => 'Cheat sheet'], 'file'],
    'a page of another space' => [['kind' => 'link', 'title' => 'Template', 'url' => 'https://example.com', 'page' => 'unknowncuid'], 'page'],
]);

test('a file must be of an accepted type and under the size limit (#1336)', function () {
    $this->actingAs($this->c['owner']);

    // An HTML page with a .pdf name: the content decides. A real file: the fake ones report the type of their name
    $path = tempnam(sys_get_temp_dir(), 'material');
    file_put_contents($path, '<!DOCTYPE html><html><head><title>x</title></head><body><script>alert(1)</script></body></html>');
    addMaterialFile($this->course, null, new UploadedFile($path, 'page.pdf', null, null, true))
        ->assertStatus(400)->assertJsonStructure(['errors' => ['file']]);
    unlink($path);
    // A PDF with an extension that is not on the list
    addMaterialFile($this->course, null, materialPdf('tool.exe'))->assertStatus(400)->assertJsonStructure(['errors' => ['file']]);
    addMaterialFile($this->course, null, UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'))
        ->assertStatus(400);

    config(['courses.materials_max_megabytes' => 1]);
    addMaterialFile($this->course, null, materialPdf('big.pdf', 1100))->assertStatus(400)->assertJsonStructure(['errors' => ['file']]);
    addMaterialFile($this->course, null, materialPdf('small.pdf', 100))->assertCreated();

    expect(CourseMaterial::query()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles('course-materials'))->toHaveCount(1);
});

test('the name of a file is cleaned: no path, no control characters (#1336)', function () {
    $this->actingAs($this->c['owner']);

    $response = addMaterialFile($this->course, null, materialPdf('..\\..\\Évaluation "finale".pdf'))->assertCreated();

    expect($response->json('data.file_name'))->toBe('Évaluation finale.pdf');
    $material = CourseMaterial::query()->firstOrFail();
    expect($material->path)->toEndWith('/evaluation-finale.pdf')->not->toContain('..');
});

test('materials exist in course spaces only, with a limit per target (#1336)', function () {
    $pages = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Pages]);
    $body = ['kind' => 'link', 'title' => 'Template', 'url' => 'https://example.com/template'];

    $this->actingAs($this->c['owner'])->postJson(materialsPath($pages), $body)
        ->assertStatus(422)->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value);
    $this->actingAs($this->c['owner'])->getJson(materialsPath($pages))->assertStatus(422);

    for ($i = 0; $i < Constants::MAX_COURSE_MATERIALS_PER_TARGET; $i++) {
        linkMaterial($this->course, $this->lesson, 'Link '.$i);
    }
    $this->actingAs($this->c['owner'])->postJson(materialsPath($this->course), [...$body, 'page' => $this->lesson->cuid])->assertStatus(422);
    // The limit is per target: the course itself still takes materials
    $this->actingAs($this->c['owner'])->postJson(materialsPath($this->course), $body)->assertCreated();
});

// ---------------------------------------------------------------------------------------------------------------------
// Read and download
// ---------------------------------------------------------------------------------------------------------------------

test('members list the materials in their order, with their lesson or section (#1336)', function () {
    $course = linkMaterial($this->course, null, 'Course');
    $section = linkMaterial($this->course, $this->section, 'Section');
    $second = linkMaterial($this->course, $this->lesson, 'Second');
    $first = linkMaterial($this->course, $this->lesson, 'First');
    $first->update(['position' => 0]);
    $second->update(['position' => 1]);

    $this->actingAs($this->c['member'])
        ->getJson(materialsPath($this->course))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.page_cuid', null);

    $this->actingAs($this->c['member'])
        ->getJson(materialsPath($this->course, null, '?page='.$this->lesson->cuid))
        ->assertOk()
        ->assertJsonPath('data.*.title', ['First', 'Second'])
        ->assertJsonPath('data.0.page_cuid', $this->lesson->cuid);

    $this->actingAs($this->c['member'])->getJson(materialsPath($this->course, null, '?page=unknowncuid'))->assertStatus(400);
    $this->actingAs($this->c['outsider'])->getJson(materialsPath($this->course))->assertForbidden();

    expect([$course->page_id, $section->page_id])->toBe([null, $this->section->id]);
});

test('a member downloads a file as an attachment that the browser cannot run (#1336)', function () {
    $this->actingAs($this->c['owner']);
    $material = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();

    $response = $this->actingAs($this->c['member'])->get(materialsPath($this->course, $material, '/download'))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('Cheat sheet.pdf');
    expect($response->headers->get('Content-Type'))->toStartWith('application/pdf');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->streamedContent())->toStartWith('%PDF-1.4');
});

test('nobody downloads a file of a course that they cannot read (#1336)', function () {
    $this->actingAs($this->c['owner']);
    $material = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();
    $download = materialsPath($this->course, $material, '/download');

    $this->app['auth']->forgetGuards();
    $this->getJson($download)->assertUnauthorized();
    $this->actingAs($this->c['outsider'])->getJson($download)->assertForbidden();

    // A material of another course of the same community: 404 (scoped binding)
    $other = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Course]);
    $this->actingAs($this->c['member'])->getJson(materialsPath($other, $material, '/download'))->assertNotFound();

    // A link has no file
    $link = linkMaterial($this->course);
    $this->actingAs($this->c['member'])->getJson(materialsPath($this->course, $link, '/download'))->assertNotFound();

    Sanctum::actingAs($this->c['member'], ['posts:read']);
    $this->getJson($download)->assertForbidden();
    Sanctum::actingAs($this->c['member'], ['pages:read']);
    $this->get($download)->assertOk();
});

test('a file on a storage service is downloaded through a temporary address (#1336)', function () {
    Storage::fake('s3');
    config(['courses.materials_disk' => 's3', 'filesystems.disks.s3.driver' => 's3']);
    $this->actingAs($this->c['owner']);
    $material = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();
    expect($material->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($material->path);
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => 'https://files.example.com/'.$path.'?signature=abc');

    $this->actingAs($this->c['member'])
        ->get(materialsPath($this->course, $material, '/download'))
        ->assertRedirect('https://files.example.com/'.$material->path.'?signature=abc');
});

// ---------------------------------------------------------------------------------------------------------------------
// Change, reorder, delete
// ---------------------------------------------------------------------------------------------------------------------

test('a manager renames a material and changes the address of a link (#1336)', function () {
    $link = linkMaterial($this->course);
    $this->actingAs($this->c['owner']);
    $file = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();

    $this->patchJson(materialsPath($this->course, $link), ['title' => 'Starter template', 'url' => 'https://example.com/new'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Starter template')
        ->assertJsonPath('data.url', 'https://example.com/new');
    $this->patchJson(materialsPath($this->course, $file), ['title' => 'The cheat sheet'])->assertOk()->assertJsonPath('data.title', 'The cheat sheet');
    $this->patchJson(materialsPath($this->course, $file), ['url' => 'https://example.com/new'])->assertStatus(400)->assertJsonStructure(['errors' => ['url']]);
    $this->patchJson(materialsPath($this->course, $link), ['title' => ''])->assertStatus(400);

    $this->actingAs($this->c['member'])->patchJson(materialsPath($this->course, $link), ['title' => 'Mine'])->assertForbidden();
    expect($link->fresh()->title)->toBe('Starter template');
});

test('a manager puts the materials of a lesson in a new order (#1336)', function () {
    $a = linkMaterial($this->course, $this->lesson, 'A');
    $b = linkMaterial($this->course, $this->lesson, 'B');
    $c = linkMaterial($this->course, $this->lesson, 'C');
    $ofCourse = linkMaterial($this->course, null, 'Course');
    $path = materialsPath($this->course, null, '/order');

    $this->actingAs($this->c['moderator'])
        ->postJson($path, ['page' => $this->lesson->cuid, 'materials' => [$c->cuid, $a->cuid, $b->cuid]])
        ->assertOk();
    expect($this->lesson->materials()->pluck('title')->all())->toBe(['C', 'A', 'B']);

    // Every material of the target, once
    $this->actingAs($this->c['moderator'])->postJson($path, ['page' => $this->lesson->cuid, 'materials' => [$c->cuid, $a->cuid]])->assertStatus(400);
    $this->actingAs($this->c['moderator'])->postJson($path, ['page' => $this->lesson->cuid, 'materials' => [$c->cuid, $a->cuid, $ofCourse->cuid]])->assertStatus(400);
    $this->actingAs($this->c['moderator'])->postJson($path, ['materials' => [$ofCourse->cuid]])->assertOk();
    $this->actingAs($this->c['member'])->postJson($path, ['materials' => [$ofCourse->cuid]])->assertForbidden();
});

test('deleting a material deletes its file (#1336)', function () {
    $this->actingAs($this->c['owner']);
    $material = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();

    $this->actingAs($this->c['member'])->deleteJson(materialsPath($this->course, $material))->assertForbidden();
    Storage::disk('local')->assertExists($material->path);

    $this->actingAs($this->c['moderator'])->deleteJson(materialsPath($this->course, $material))->assertNoContent();

    expect($material->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($material->path);
    expect(Storage::disk('local')->allFiles('course-materials'))->toBe([]);
});

test('the files go with the lesson, the space and the community (#1336, data rule 8)', function () {
    $this->actingAs($this->c['owner']);
    $ofLesson = CourseMaterial::query()->where('cuid', addMaterialFile($this->course, $this->lesson)->json('data.cuid'))->firstOrFail();
    $ofCourse = CourseMaterial::query()->where('cuid', addMaterialFile($this->course)->json('data.cuid'))->firstOrFail();
    $otherCourse = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Course]);
    $ofOtherCourse = CourseMaterial::query()->where('cuid', addMaterialFile($otherCourse)->json('data.cuid'))->firstOrFail();

    app(DeletesPages::class)->delete($this->c['owner'], $this->lesson);
    expect($ofLesson->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($ofLesson->path);
    Storage::disk('local')->assertExists($ofCourse->path);

    app(DeletesSpaces::class)->delete($this->c['owner'], $this->course);
    expect($ofCourse->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($ofCourse->path);
    Storage::disk('local')->assertExists($ofOtherCourse->path);

    app(DeletesCommunities::class)->delete($this->c['owner'], $this->c['community']);
    Storage::disk('local')->assertMissing($ofOtherCourse->path);
    expect(Storage::disk('local')->allFiles('course-materials'))->toBe([]);
});

test('the database refuses a material that is both a file and a link (#1336)', function () {
    expect(fn () => DB::transaction(fn () => CourseMaterial::query()->create([
        'community_id' => $this->course->community_id,
        'space_id' => $this->course->id,
        'title' => 'Both',
        'kind' => KnowiiCourseMaterialKind::Link,
        'url' => 'https://example.com',
        'disk' => 'local',
        'path' => 'course-materials/x/y/z.pdf',
        'file_name' => 'z.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
    ])))->toThrow(QueryException::class);
});
