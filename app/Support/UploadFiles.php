<?php

namespace App\Support;

use App\Constants;
use Illuminate\Support\Facades\Storage;

/**
 * The files of an uploaded image (#1178, #1207): the original, and its smaller versions next to it
 * (`<cuid>.webp`, `<cuid>-640.webp`, `<cuid>-1280.webp`, ADR 0040). Every place that deletes an upload deletes its
 * files here, so a version is never left behind.
 */
final class UploadFiles
{
    /**
     * The path of the version of an image that is `$width` pixels wide.
     */
    public static function sizePath(string $path, int $width): string
    {
        return (string) preg_replace('/\.([a-z0-9]+)$/i', '-'.$width.'.$1', $path);
    }

    /**
     * Every path that can exist for an upload. The sizes that are not built yet (null), or that were written before
     * a crash, are included: deleting a missing file does nothing.
     *
     * @param  list<int>|null  $sizes
     * @return list<string>
     */
    public static function paths(string $path, ?array $sizes): array
    {
        $paths = [$path];
        foreach (array_unique(array_merge(Constants::UPLOAD_IMAGE_SIZES, $sizes ?? [])) as $width) {
            $paths[] = self::sizePath($path, $width);
        }

        return $paths;
    }

    /**
     * @param  list<int>|null  $sizes
     */
    public static function delete(string $disk, string $path, ?array $sizes): void
    {
        Storage::disk($disk)->delete(self::paths($path, $sizes));
    }
}
