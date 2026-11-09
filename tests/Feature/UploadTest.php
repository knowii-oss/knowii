<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Uploads\UploadsImages;
use App\Models\Upload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
});

function uploadsPath(): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid]);
}

test('members upload images that are re-encoded and scaled down', function () {
    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => UploadedFile::fake()->image('photo.jpg', 3000, 1500),
        'alt' => '  A wide landscape  ',
    ]);

    expect($upload->width)->toBe(Constants::MAX_UPLOAD_IMAGE_DIMENSION)
        ->and($upload->height)->toBe(1000)
        ->and($upload->mime_type)->toBe('image/webp')
        ->and($upload->alt)->toBe('A wide landscape')
        ->and($upload->path)->toStartWith("uploads/{$this->c['community']->cuid}/")
        ->and($upload->path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($upload->path);

    $stored = Storage::disk('public')->get($upload->path);
    expect(getimagesizefromstring((string) $stored)['mime'])->toBe('image/webp');
});

test('small images keep their size and PNG transparency survives', function () {
    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => UploadedFile::fake()->image('logo.png', 120, 80),
        'alt' => 'Logo',
    ]);

    expect([$upload->width, $upload->height])->toBe([120, 80]);
});

test('metadata is removed by the re-encoding', function () {
    // A JPEG with a comment segment, which a copy would keep
    $image = imagecreatetruecolor(50, 50);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    $withComment = substr($jpeg, 0, 2)."\xFF\xFE\x00\x10SECRET-LOCATION".substr($jpeg, 2);
    $path = tempnam(sys_get_temp_dir(), 'jpg');
    file_put_contents($path, $withComment);

    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true),
        'alt' => 'Black square',
    ]);

    expect(Storage::disk('public')->get($upload->path))->not->toContain('SECRET-LOCATION');
});

test('only members upload, and only images with an alt text', function () {
    expect(fn () => app(UploadsImages::class)->upload($this->c['outsider'], $this->c['community'], ['file' => UploadedFile::fake()->image('a.png'), 'alt' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], ['file' => UploadedFile::fake()->image('a.png')]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), 'alt' => 'x']))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], ['file' => UploadedFile::fake()->image('big.png')->size(Constants::MAX_UPLOAD_SIZE_KILOBYTES + 1), 'alt' => 'x']))
        ->toThrow(ValidationException::class);
});

test('a file that only claims to be an image is refused', function () {
    $path = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($path, '<?php echo "not an image";');

    app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => new UploadedFile($path, 'fake.png', 'image/png', null, true),
        'alt' => 'x',
    ]);
})->throws(ValidationException::class);

test('the API accepts multipart uploads on this endpoint only', function () {
    RateLimiter::clear('uploads');
    $this->actingAs($this->c['member']);

    $this->post(uploadsPath(), ['file' => UploadedFile::fake()->image('a.png', 40, 30), 'alt' => 'Tiny'], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.width', 40)
        ->assertJsonPath('data.alt', 'Tiny')
        ->assertJsonMissingPath('data.path');

    expect(Upload::count())->toBe(1);

    // Other endpoints keep refusing non-JSON bodies
    $this->post(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]), ['name' => 'x'], [
        'Accept' => 'application/json',
        'Content-Type' => 'multipart/form-data; boundary=abc',
    ])->assertStatus(415);
});

test('outsiders get a 403 from the upload API', function () {
    $this->actingAs($this->c['outsider'])
        ->post(uploadsPath(), ['file' => UploadedFile::fake()->image('a.png'), 'alt' => 'x'], ['Accept' => 'application/json'])
        ->assertForbidden();
});
