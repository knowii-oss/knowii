<?php

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Support\UploadReferences;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    Storage::fake('public');
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
});

function uploadAnImage(): Upload
{
    return app(UploadsImages::class)->upload(test()->c['member'], test()->c['community'], [
        'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        'alt' => 'A photo',
    ]);
}

function imageMarkdown(Upload $upload): string
{
    return "![{$upload->alt}]({$upload->url()})";
}

test('the upload references of a Markdown text are found whatever the host (#1201)', function () {
    $markdown = 'Look ![a](/storage/uploads/c1/2026/11/abc123.webp) and [b](https://cdn.example.com/uploads/c1/2026/10/DEF456.png) '
        .'and ![a](/storage/uploads/c1/2026/11/abc123.webp) but not /storage/resources/r1/cover.webp nor uploads/c1/abc.webp';

    expect(UploadReferences::cuidsIn($markdown))->toBe(['abc123', 'def456'])
        ->and(UploadReferences::cuidsIn('No image'))->toBe([]);
});

test('posts record the uploads they show, and forget the ones removed from the text (#1201)', function () {
    $first = uploadAnImage();
    $second = uploadAnImage();

    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'Two photos', 'body' => imageMarkdown($first)."\n\n".imageMarkdown($second)]);
    expect($first->usages()->count())->toBe(1)->and($second->usages()->count())->toBe(1);

    app(UpdatesPosts::class)->update($this->c['member'], $post, ['body' => imageMarkdown($second)]);
    expect($first->usages()->count())->toBe(0)->and($second->usages()->count())->toBe(1);
});

test('comments record the uploads they show (#1201)', function () {
    $upload = uploadAnImage();
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'A post', 'body' => 'Text']);

    $comment = app(CreatesComments::class)->create($this->c['admin'], $post, ['body' => 'Quote: '.imageMarkdown($upload)]);
    expect($upload->usages()->where('comment_id', $comment->id)->exists())->toBeTrue();

    app(UpdatesComments::class)->update($this->c['admin'], $comment, ['body' => 'No image any more']);
    expect($upload->usages()->count())->toBe(0);
});

test('the prune deletes the old uploads that nothing uses, file and row, and keeps the others (#1201)', function () {
    $used = uploadAnImage();
    $unused = uploadAnImage();
    $recent = uploadAnImage();
    app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'A photo', 'body' => imageMarkdown($used)]);
    Upload::query()->whereKey([$used->id, $unused->id])->update(['created_at' => now()->subHours(25)]);

    $this->artisan('uploads:prune')->expectsOutputToContain('Unused uploads deleted: 1')->assertSuccessful();

    expect(Upload::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(Upload::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(Upload::query()->whereKey($recent->id)->exists())->toBeTrue();
    Storage::disk('public')->assertMissing($unused->path);
    Storage::disk('public')->assertExists($used->path);
    Storage::disk('public')->assertExists($recent->path);
});

test('the dry run of the prune deletes nothing (#1201)', function () {
    $unused = uploadAnImage();
    Upload::query()->whereKey($unused->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('uploads:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete '.$unused->path)
        ->expectsOutputToContain('Unused uploads found: 1')
        ->assertSuccessful();

    expect(Upload::query()->whereKey($unused->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($unused->path);
});

test('the images of a deleted post are pruned the next day (#1201)', function () {
    $upload = uploadAnImage();
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'A photo', 'body' => imageMarkdown($upload)]);

    app(DeletesPosts::class)->delete($this->c['member'], $post);
    $this->travel(25)->hours();
    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($upload->path);
});

test('the prune runs every day (#1201)', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('uploads:prune')->assertSuccessful();
});
