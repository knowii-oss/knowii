<?php

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
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
    Upload::query()->whereKey([$used->id, $unused->id])->update(['created_at' => now()->subDays(8)]);

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
    Upload::query()->whereKey($unused->id)->update(['created_at' => now()->subDays(8)]);

    $this->artisan('uploads:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete '.$unused->path)
        ->expectsOutputToContain('Unused uploads found: 1')
        ->assertSuccessful();

    expect(Upload::query()->whereKey($unused->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($unused->path);
});

test('an image of a draft kept open for days stays: unused uploads are kept 7 days (#1273)', function () {
    $draftImage = uploadAnImage();
    Upload::query()->whereKey($draftImage->id)->update(['created_at' => now()->subDays(6)]);

    $this->artisan('uploads:prune')->expectsOutputToContain('Unused uploads deleted: 0')->assertSuccessful();

    expect(Upload::query()->whereKey($draftImage->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($draftImage->path);
});

test('the images of a deleted post are pruned after 7 days (#1201, #1273)', function () {
    $upload = uploadAnImage();
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'A photo', 'body' => imageMarkdown($upload)]);

    app(DeletesPosts::class)->delete($this->c['member'], $post);
    $this->travel(8)->days();
    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($upload->path);
});

test('an uploaded image lives as long as content of its own community shows it', function () {
    $upload = uploadAnImage();
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'A photo', 'body' => imageMarkdown($upload)]);

    // Another account shows the same image in a post and in a comment of its own community
    $other = User::factory()->withUserProfile()->create();
    $otherCommunity = Community::factory()->create(['owner_id' => $other->id, 'visibility' => KnowiiCommunityVisibility::Private]);
    $otherSpace = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $otherCommunity->id]))->create(['type' => KnowiiSpaceType::Discussion]);
    $quote = app(CreatesPosts::class)->create($other, $otherSpace, ['title' => 'A quote', 'body' => imageMarkdown($upload)]);
    $comment = app(CreatesComments::class)->create($other, $quote, ['body' => 'Again: '.imageMarkdown($upload)]);
    app(UpdatesPosts::class)->update($other, $quote, ['body' => 'Changed: '.imageMarkdown($upload)]);

    // The text shows the image while it exists, and records nothing: the image is not of this community
    expect($quote->fresh()->body_html)->toContain($upload->cuid)
        ->and($quote->uploadUsages()->count())->toBe(0)
        ->and($comment->uploadUsages()->count())->toBe(0)
        ->and($upload->usages()->count())->toBe(1)
        ->and($upload->usages()->where('post_id', $post->id)->exists())->toBeTrue();

    // The author deletes the post: 7 days later the file goes, whatever another community shows
    app(DeletesPosts::class)->delete($this->c['member'], $post);
    $this->travel(8)->days();
    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($upload->path);
});

test('an upload of the community is recorded whoever uploaded it', function () {
    $upload = uploadAnImage();

    $post = app(CreatesPosts::class)->create($this->c['admin'], $this->space, ['title' => 'A quote', 'body' => imageMarkdown($upload)]);

    expect($upload->usages()->where('post_id', $post->id)->exists())->toBeTrue();
});

test('the prune runs every day (#1201)', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('uploads:prune')->assertSuccessful();
});
