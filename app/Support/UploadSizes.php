<?php

namespace App\Support;

use App\Constants;
use Illuminate\Support\Facades\Storage;

/**
 * Builds and stores the smaller versions of an uploaded image (#1207, ADR 0040), next to the original.
 */
final class UploadSizes
{
    /**
     * The widths that an image of this width gets: the wanted widths that are smaller than the image.
     *
     * @return list<int>
     */
    public static function widthsFor(int $imageWidth): array
    {
        return array_values(array_filter(Constants::UPLOAD_IMAGE_SIZES, static fn (int $width): bool => $width < $imageWidth));
    }

    /**
     * Write the versions of the image to the disk.
     *
     * @param  string  $contents  The original file, already processed
     * @return list<int> The widths that exist now (empty when the image is small)
     */
    public static function store(string $disk, string $path, string $contents, int $imageWidth): array
    {
        $widths = self::widthsFor($imageWidth);
        if ($widths === []) {
            return [];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $stored = [];
        foreach (ImageProcessor::smallerVersions($contents, $widths, $extension) as $width => $version) {
            Storage::disk($disk)->put(UploadFiles::sizePath($path, $width), $version, ['visibility' => 'public']);
            $stored[] = $width;
        }

        return $stored;
    }
}
