<?php

namespace App\Actions\Uploads;

use App\Constants;
use App\Contracts\Uploads\UploadsImages;
use App\Models\Community;
use App\Models\Upload;
use App\Models\User;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Visus\Cuid2\Cuid2;

class UploadImage implements UploadsImages
{
    /**
     * Store an image for a post or a comment: checked, re-encoded (no metadata), scaled down, with its alt text.
     */
    final public function upload(User $user, Community $community, array $input): Upload
    {
        Gate::forUser($user)->authorize('uploadImage', $community);

        /** @var array{file: UploadedFile, alt: string} $validated */
        $validated = Validator::make($input, [
            // WARNING: aligned with uploadFormSchema (upload.schema.ts)
            'file' => ['required', 'file', 'max:'.Constants::MAX_UPLOAD_SIZE_KILOBYTES, 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
            'alt' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_UPLOAD_ALT],
        ])->validate();

        $image = ImageProcessor::process($validated['file']->getRealPath());

        $cuid = (new Cuid2)->toString();
        $disk = config('filesystems.uploads_disk');
        $disk = is_string($disk) && $disk !== '' ? $disk : 'public';
        $path = sprintf('uploads/%s/%s/%s.%s', $community->cuid, now()->format('Y/m'), $cuid, $image['extension']);

        Storage::disk($disk)->put($path, $image['contents'], ['visibility' => 'public']);

        $upload = Upload::create([
            'cuid' => $cuid,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $image['mime_type'],
            'width' => $image['width'],
            'height' => $image['height'],
            'size_bytes' => strlen($image['contents']),
            'alt' => trim($validated['alt']),
        ]);

        Log::info('Image uploaded', ['upload' => $upload->cuid, 'community' => $community->cuid]);

        return $upload;
    }
}
