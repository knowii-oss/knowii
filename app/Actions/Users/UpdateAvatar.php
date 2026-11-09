<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\UpdatesAvatars;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Visus\Cuid2\Cuid2;

class UpdateAvatar implements UpdatesAvatars
{
    /**
     * Store an avatar (#109, ADR 0021): checked, re-encoded without metadata, cropped to the centered square and scaled
     * to AVATAR_SIZE pixels. Each new avatar gets a new file name (no stale browser cache); the old file is deleted.
     *
     * @throws ValidationException
     * @throws BusinessException When the file is not a readable image
     */
    final public function update(User $user, array $input): User
    {
        /** @var array{file: UploadedFile} $validated */
        $validated = Validator::make($input, [
            'file' => ['required', 'file', 'max:'.Constants::MAX_UPLOAD_SIZE_KILOBYTES, 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
        ])->validate();

        $image = ImageProcessor::process($validated['file']->getRealPath(), Constants::AVATAR_SIZE, true);

        $disk = self::disk();
        $path = sprintf('avatars/%s/%s.%s', $user->cuid, (new Cuid2)->toString(), $image['extension']);
        Storage::disk($disk)->put($path, $image['contents'], ['visibility' => 'public']);

        $previous = $user->getAttributes()['avatar_path'] ?? null;
        $user->forceFill(['avatar_path' => $path])->save();
        if (is_string($previous) && $previous !== '') {
            Storage::disk($disk)->delete($previous);
        }

        Log::info('Avatar updated', ['user' => $user->cuid]);

        return $user;
    }

    final public function delete(User $user): User
    {
        $previous = $user->getAttributes()['avatar_path'] ?? null;
        if (is_string($previous) && $previous !== '') {
            Storage::disk(self::disk())->delete($previous);
        }
        $user->forceFill(['avatar_path' => null])->save();

        Log::info('Avatar removed', ['user' => $user->cuid]);

        return $user;
    }

    /**
     * The disk of the uploads (#1178): avatars live next to the images of posts.
     */
    public static function disk(): string
    {
        $disk = config('filesystems.uploads_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }
}
