<?php

use App\Constants;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Uploads\BuildsUploadSizes;
use App\Contracts\Uploads\UploadsImages;
use App\Models\Upload;
use App\Rules\MaxMarkdownImages;
use App\Support\ImageProcessor;
use App\Support\Markdown;
use App\Support\UploadReferences;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Visus\Cuid2\Cuid2;

/*
 * Stored sizes of uploaded images, with srcset (#1207, ADR 0040)
 */

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
});

/**
 * A real image file (GD), because fake uploaded files report the MIME type of their name
 */
function sizesImageFile(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 80, 40));
    $path = (string) tempnam(sys_get_temp_dir(), 'img');
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

function sizesUploadOf(int $width, int $height): Upload
{
    return app(UploadsImages::class)->upload(test()->c['member'], test()->c['community'], [
        'file' => new UploadedFile(sizesImageFile($width, $height), 'photo.png', 'image/png', null, true),
        'alt' => 'A photo',
    ]);
}

/**
 * An upload of before #1207: the original only, and sizes is null
 */
function sizesLegacyUpload(int $width, int $height, ?string $contents = null): Upload
{
    $cuid = (new Cuid2)->toString();
    $path = sprintf('uploads/%s/2026/10/%s.webp', test()->c['community']->cuid, $cuid);
    $contents ??= ImageProcessor::process(sizesImageFile($width, $height))['contents'];
    Storage::disk('public')->put($path, $contents);

    return Upload::create([
        'cuid' => $cuid,
        'community_id' => test()->c['community']->id,
        'user_id' => test()->c['member']->id,
        'disk' => 'public',
        'path' => $path,
        'mime_type' => 'image/webp',
        'width' => $width,
        'height' => $height,
        'size_bytes' => strlen($contents),
        'alt' => 'Old photo',
    ]);
}

function sizesImage(Upload $upload, string $label = 'A photo'): string
{
    return "![$label]({$upload->url()})";
}

function sizesWidthOf(string $path): int
{
    return (int) getimagesizefromstring((string) Storage::disk('public')->get($path))[0];
}

test('a large image gets two smaller versions at upload, next to the original (#1207)', function () {
    $upload = sizesUploadOf(3000, 1500);

    expect($upload->width)->toBe(2000)
        ->and($upload->sizes)->toBe([640, 1280])
        ->and($upload->sizePath(640))->toBe(str_replace('.webp', '-640.webp', $upload->path))
        ->and($upload->sizeUrl(1280))->toEndWith('-1280.webp')
        ->and(sizesWidthOf($upload->path))->toBe(2000)
        ->and(sizesWidthOf($upload->sizePath(640)))->toBe(640)
        ->and(sizesWidthOf($upload->sizePath(1280)))->toBe(1280)
        ->and(getimagesizefromstring((string) Storage::disk('public')->get($upload->sizePath(640)))[1])->toBe(320)
        ->and(getimagesizefromstring((string) Storage::disk('public')->get($upload->sizePath(1280)))['mime'])->toBe('image/webp');
    expect(json_decode((string) DB::table('uploads')->where('id', $upload->id)->value('sizes'), true))->toBe([640, 1280]);
});

test('an image is never upscaled: only the widths smaller than the image are stored (#1207)', function () {
    $medium = sizesUploadOf(1000, 500);
    $small = sizesUploadOf(500, 300);
    $exact = sizesUploadOf(640, 300);

    expect($medium->sizes)->toBe([640])
        ->and($small->sizes)->toBe([])
        // The same width is not smaller
        ->and($exact->sizes)->toBe([]);
    Storage::disk('public')->assertExists($medium->sizePath(640));
    Storage::disk('public')->assertMissing($medium->sizePath(1280));
    Storage::disk('public')->assertMissing($small->sizePath(640));
    expect(Constants::UPLOAD_IMAGE_SIZES)->toBe([640, 1280]);
});

test('an animated GIF is flattened like the original, and so are its sizes (#1207)', function () {
    $frame = imagecreatetruecolor(1500, 800);
    $path = (string) tempnam(sys_get_temp_dir(), 'gif');
    imagegif($frame, $path);

    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => new UploadedFile($path, 'anim.gif', 'image/gif', null, true),
        'alt' => 'Animated',
    ]);

    expect($upload->mime_type)->toBe('image/webp')
        ->and($upload->sizes)->toBe([640, 1280])
        ->and(getimagesizefromstring((string) Storage::disk('public')->get($upload->sizePath(640)))['mime'])->toBe('image/webp');
});

test('an address with a size suffix still gives the cuid of its upload (#1207)', function () {
    $markdown = '![a](/storage/uploads/c1/2026/11/abc123-640.webp) ![b](/storage/uploads/c1/2026/11/abc123-1280.webp) ![c](/storage/uploads/c1/2026/11/def456.webp)';

    expect(UploadReferences::cuidsIn($markdown))->toBe(['abc123', 'def456']);
});

test('a gallery gets width, height, srcset and sizes for its uploads, and the link stays the original (#1207)', function () {
    $first = sizesUploadOf(3000, 1500);
    $second = sizesUploadOf(1000, 1000);

    $html = Markdown::render(sizesImage($first, 'The first')."\n".sizesImage($second, 'The second'));
    $tile = '(min-width: 42rem) 21rem, 50vw';

    expect($html)->toContain('<a class="image-gallery-item" href="'.$first->url().'"><img src="'.$first->url().'" alt="The first" loading="lazy" decoding="async" width="2000" height="1000" srcset="'.$first->sizeUrl(640).' 640w, '.$first->sizeUrl(1280).' 1280w, '.$first->url().' 2000w" sizes="'.$tile.'" /></a>')
        ->and($html)->toContain('width="1000" height="1000" srcset="'.$second->sizeUrl(640).' 640w, '.$second->url().' 1000w" sizes="'.$tile.'"')
        ->and($html)->not->toContain('style=');
});

test('one image alone, and an image in a sentence, use the sizes of a single image (#1207)', function () {
    $upload = sizesUploadOf(3000, 1500);
    $single = '(min-width: 48rem) 48rem, 100vw';
    $srcset = 'srcset="'.$upload->sizeUrl(640).' 640w, '.$upload->sizeUrl(1280).' 1280w, '.$upload->url().' 2000w" sizes="'.$single.'"';

    $alone = Markdown::render(sizesImage($upload));
    $inline = Markdown::render('Look at '.sizesImage($upload).' here.');
    $linked = Markdown::render('[!['.$upload->alt.']('.$upload->url().')](https://example.com)');

    expect($alone)->toContain('data-image-count="1"')->and($alone)->toContain('width="2000" height="1000" '.$srcset)
        ->and($inline)->not->toContain('image-gallery')
        ->and($inline)->toContain('<p>Look at <img src="'.$upload->url().'" alt="A photo" width="2000" height="1000" '.$srcset.' /> here.</p>')
        ->and($linked)->toContain('width="2000" height="1000" '.$srcset);
});

test('a small upload gets its size, and no srcset (#1207)', function () {
    $small = sizesUploadOf(300, 200);

    $html = Markdown::render('See '.sizesImage($small).' now');

    expect($html)->toContain('width="300" height="200"')->and($html)->not->toContain('srcset')->and($html)->not->toContain('sizes=');
});

test('an upload of before the sizes, not built yet, gets its size only (#1207)', function () {
    $old = sizesLegacyUpload(1500, 900);

    $html = Markdown::render(sizesImage($old));

    expect($html)->toContain('width="1500" height="900"')->and($html)->not->toContain('srcset');
});

test('an image that is not an upload, or that borrows the cuid of one, gets no attributes (#1207)', function () {
    $upload = sizesUploadOf(3000, 1500);
    $path = $upload->path;
    $cuid = $upload->cuid;

    $others = [
        'another site' => '![x](https://example.com/photo.webp)',
        'another site with the path' => "![x](https://evil.example.com/storage/{$path})",
        'protocol relative' => "![x](//evil.example.com/storage/{$path})",
        'another path with the cuid' => "![x](/storage/uploads/other/2026/11/{$cuid}.webp)",
        'the file of a size' => '![x]('.$upload->sizeUrl(640).')',
        'a query' => '![x]('.$upload->url().'?x=1)',
        'an unknown cuid' => '![x](/storage/uploads/'.$this->c['community']->cuid.'/2026/11/unknowncuid.webp)',
    ];

    foreach ($others as $markdown) {
        foreach ([$markdown, 'Text '.$markdown.' more'] as $text) {
            $html = Markdown::render($text);
            expect($html)->toContain('<img ')->and($html)->not->toContain('width=')->and($html)->not->toContain('srcset');
        }
    }

    // The same address with the host of the application is the upload
    $own = '![x](http://localhost'.parse_url($upload->url(), PHP_URL_PATH).')';
    config(['app.url' => 'http://localhost']);
    expect(Markdown::render($own))->toContain('srcset=');
});

test('an unsafe address keeps being refused, and gets nothing (#1207)', function () {
    $html = Markdown::render('Text ![x](javascript:alert(1)) more');

    expect($html)->toContain('src=""')->and($html)->not->toContain('javascript')->and($html)->not->toContain('width=');
});

test('one query finds the uploads of all the images of a text (#1207)', function () {
    $uploads = [sizesUploadOf(3000, 1500), sizesUploadOf(2500, 1000), sizesUploadOf(900, 900), sizesUploadOf(100, 100)];
    $markdown = sizesImage($uploads[0])."\n".sizesImage($uploads[1])."\n".sizesImage($uploads[2])."\n\nText ".sizesImage($uploads[3]).' and '.sizesImage($uploads[0])."\n\n![x](https://example.com/a.webp)";

    DB::enableQueryLog();
    $html = Markdown::render($markdown);
    $queries = array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/from\s+[`"]?uploads[`"]?(\s|$)/', (string) $query['query']) === 1);
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)->and(substr_count($html, 'srcset='))->toBe(4);
});

test('a text without upload images makes no query, and the image limit counts the same as before (#1207)', function () {
    $upload = sizesUploadOf(3000, 1500);
    $markdown = sizesImage($upload)."\n".sizesImage($upload)."\n\nText ".sizesImage($upload).' and ![x](https://example.com/a.webp)';

    DB::enableQueryLog();
    $none = Markdown::render('![x](https://example.com/a.webp) and no upload');
    $count = MaxMarkdownImages::count($markdown);
    $queries = array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/from\s+[`"]?uploads[`"]?(\s|$)/', (string) $query['query']) === 1);
    DB::disableQueryLog();

    expect($queries)->toHaveCount(0)
        ->and($none)->toContain('<img ')
        ->and($count)->toBe(4)
        ->and(substr_count(Markdown::render($markdown), '<img '))->toBe(4);
});

test('the prune deletes the smaller versions with the original (#1207)', function () {
    $unused = sizesUploadOf(3000, 1500);
    $legacy = sizesLegacyUpload(1500, 900);
    // A version that exists before the list says so (a crash between the files and the row)
    Storage::disk('public')->put($legacy->sizePath(640), 'left over');
    Upload::query()->whereKey([$unused->id, $legacy->id])->update(['created_at' => now()->subDays(8)]);

    $this->artisan('uploads:prune')->assertSuccessful();

    foreach ([$unused->path, $unused->sizePath(640), $unused->sizePath(1280), $legacy->path, $legacy->sizePath(640)] as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    expect(Upload::query()->count())->toBe(0);
});

test('the dry run of the prune keeps the smaller versions (#1207)', function () {
    $unused = sizesUploadOf(3000, 1500);
    Upload::query()->whereKey($unused->id)->update(['created_at' => now()->subDays(8)]);

    $this->artisan('uploads:prune', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('public')->assertExists($unused->sizePath(640));
    Storage::disk('public')->assertExists($unused->sizePath(1280));
});

test('deleting a community deletes the smaller versions of its uploads (#1207, #1265)', function () {
    $upload = sizesUploadOf(3000, 1500);
    $legacy = sizesLegacyUpload(1500, 900);

    app(DeletesCommunities::class)->delete($this->c['owner'], $this->c['community']);

    foreach ([$upload->path, $upload->sizePath(640), $upload->sizePath(1280), $legacy->path] as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

test('deleting an account deletes the smaller versions of the uploads of the communities it owns (#1207, #1265)', function () {
    $owner = $this->c['owner'];
    $upload = app(UploadsImages::class)->upload($owner, $this->c['community'], [
        'file' => new UploadedFile(sizesImageFile(3000, 1500), 'photo.png', 'image/png', null, true),
        'alt' => 'A photo',
    ]);

    $this->actingAs($owner)->delete('/user', ['password' => 'password']);

    Storage::disk('public')->assertMissing($upload->path);
    Storage::disk('public')->assertMissing($upload->sizePath(640));
    Storage::disk('public')->assertMissing($upload->sizePath(1280));
});

test('uploads:build-sizes builds the missing sizes of the old uploads, and a second run does nothing more (#1207)', function () {
    $large = sizesLegacyUpload(1500, 900);
    $medium = sizesLegacyUpload(700, 400);
    $small = sizesLegacyUpload(300, 200);
    $done = sizesUploadOf(3000, 1500);

    $this->artisan('uploads:build-sizes')->assertSuccessful();

    expect($large->fresh()->sizes)->toBe([640, 1280])
        ->and($medium->fresh()->sizes)->toBe([640])
        ->and($small->fresh()->sizes)->toBe([])
        ->and($done->fresh()->sizes)->toBe([640, 1280])
        ->and(sizesWidthOf($large->sizePath(1280)))->toBe(1280)
        ->and(sizesWidthOf($medium->sizePath(640)))->toBe(640);
    Storage::disk('public')->assertMissing($medium->sizePath(1280));
    Storage::disk('public')->assertMissing($small->sizePath(640));

    // Again: nothing is left to do, and the files are not written again
    Storage::disk('public')->put($large->sizePath(640), 'marker');
    expect(app(BuildsUploadSizes::class)->build())->toBe(['built' => 0, 'small' => 0, 'skipped' => 0])
        ->and(Storage::disk('public')->get($large->sizePath(640)))->toBe('marker');
});

test('the dry run of uploads:build-sizes writes nothing (#1207)', function () {
    $large = sizesLegacyUpload(1500, 900);

    $this->artisan('uploads:build-sizes', ['--dry-run' => true])
        ->expectsOutputToContain('Would build 640, 1280 for '.$large->path)
        ->assertSuccessful();

    expect($large->fresh()->sizes)->toBeNull();
    Storage::disk('public')->assertMissing($large->sizePath(640));
    Storage::disk('public')->assertMissing($large->sizePath(1280));
});

test('a file that is missing or cannot be read is reported and skipped, never deleted (#1207)', function () {
    $missing = sizesLegacyUpload(1500, 900);
    Storage::disk('public')->delete($missing->path);
    $broken = sizesLegacyUpload(1500, 900, 'this is not an image');
    $good = sizesLegacyUpload(1500, 900);
    $lines = [];

    $counts = app(BuildsUploadSizes::class)->build(false, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($counts)->toBe(['built' => 1, 'small' => 0, 'skipped' => 2])
        ->and($lines)->toHaveCount(2)
        ->and($lines[0])->toContain($missing->path)
        ->and($lines[1])->toContain($broken->path)
        ->and($good->fresh()->sizes)->toBe([640, 1280])
        // Still waiting for a next run
        ->and($missing->fresh()->sizes)->toBeNull()
        ->and($broken->fresh()->sizes)->toBeNull()
        ->and(Upload::query()->count())->toBe(3);
    Storage::disk('public')->assertExists($broken->path);
});
