<?php

namespace App\Support;

use App\Constants;
use App\Exceptions\BusinessException;
use GdImage;

/**
 * Re-encodes uploaded images (#1178): the output is a new file, so metadata (EXIF: location, camera) is dropped.
 * Large images are scaled down; transparency is kept.
 */
final class ImageProcessor
{
    /**
     * @return array{contents: string, mime_type: string, extension: string, width: int, height: int}
     *
     * @throws BusinessException When the file is not a supported image or is too large in pixels
     */
    public static function process(string $sourcePath): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new BusinessException('This file is not a supported image (JPEG, PNG, WebP or GIF)');
        }

        [$width, $height] = [$info[0], $info[1]];
        // Refuse decompression bombs before decoding: a small file can expand to gigabytes of pixels
        if ($width < 1 || $height < 1 || $width * $height > Constants::MAX_UPLOAD_IMAGE_PIXELS) {
            throw new BusinessException('This image has too many pixels');
        }

        $contents = file_get_contents($sourcePath);
        $image = $contents === false ? false : @imagecreatefromstring($contents);
        if (! $image instanceof GdImage) {
            throw new BusinessException('This image cannot be read');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = self::applyExifOrientation($image, $sourcePath);
        }

        $image = self::scaleDown($image, Constants::MAX_UPLOAD_IMAGE_DIMENSION);
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $webp = function_exists('imagewebp');
        $webp ? imagewebp($image, null, Constants::UPLOAD_IMAGE_QUALITY) : imagepng($image, null, 6);
        $output = (string) ob_get_clean();

        $result = [
            'contents' => $output,
            'mime_type' => $webp ? 'image/webp' : 'image/png',
            'extension' => $webp ? 'webp' : 'png',
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
        imagedestroy($image);

        return $result;
    }

    private static function scaleDown(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largest = max($width, $height);

        if ($largest <= $maxDimension) {
            return $image;
        }

        $ratio = $maxDimension / $largest;
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);

        return $scaled instanceof GdImage ? $scaled : $image;
    }

    /**
     * Phone photos store their rotation in EXIF; the re-encoded file has no EXIF, so apply it to the pixels.
     */
    private static function applyExifOrientation(GdImage $image, string $sourcePath): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($sourcePath) : false;
        $orientation = is_array($exif) && is_numeric($exif['Orientation'] ?? null) ? (int) $exif['Orientation'] : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
