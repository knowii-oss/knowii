<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The files of a community resource (ADR 0018): the images of its captured article, under
 * `community-resources/{cuid}/` on the uploads disk. They belong to one community and go with the community resource.
 */
final class CommunityResourceFiles
{
    public static function folder(string $communityResourceCuid): string
    {
        return 'community-resources/'.$communityResourceCuid;
    }

    /**
     * @param  list<string>  $communityResourceCuids
     */
    public static function delete(array $communityResourceCuids): void
    {
        $disk = Storage::disk(self::disk());
        foreach ($communityResourceCuids as $cuid) {
            $disk->deleteDirectory(self::folder($cuid));
        }
    }

    public static function disk(): string
    {
        $disk = config('filesystems.uploads_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }
}
