<?php

namespace App\Support;

use App\Constants;
use App\Exceptions\BusinessException;
use App\Models\Resource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The images of a resource (#1063, ADR 0016): a cover thumbnail and the icon of the site, re-encoded (no metadata,
 * no active content) and stored on the uploads disk. Pages show them from the Knowii origin: no hotlinking, no
 * third-party request from the browsers of the members, no CSP exception.
 */
final class ResourceImages
{
    /**
     * Store the downloaded images of a resource. An image that cannot be read is skipped: images are a bonus.
     */
    public static function store(Resource $resource, ?string $coverBytes, ?string $faviconBytes): void
    {
        $changes = [];

        $coverPath = self::put($resource, 'cover', $coverBytes, Constants::RESOURCE_COVER_MAX_DIMENSION);
        if ($coverPath !== null) {
            $changes['cover_image_path'] = $coverPath;
        }

        $faviconPath = self::put($resource, 'favicon', $faviconBytes, Constants::RESOURCE_FAVICON_MAX_DIMENSION);
        if ($faviconPath !== null) {
            $changes['favicon_path'] = $faviconPath;
        }

        if ($changes !== []) {
            $resource->update($changes);
        }
    }

    /**
     * Delete the image folder of a resource that no community uses any more (#1265).
     */
    public static function deleteAll(Resource $resource): void
    {
        Storage::disk(self::disk())->deleteDirectory('resources/'.$resource->cuid);
    }

    /**
     * Public URL of a stored image, or null.
     */
    public static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(self::disk())->url($path);
    }

    private static function put(Resource $resource, string $name, ?string $bytes, int $maxDimension): ?string
    {
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $source = tempnam(sys_get_temp_dir(), 'knowii-resource-image-');
        if ($source === false) {
            return null;
        }

        try {
            file_put_contents($source, $bytes);
            $image = ImageProcessor::process($source, $maxDimension);
            $path = sprintf('resources/%s/%s.%s', $resource->cuid, $name, $image['extension']);
            Storage::disk(self::disk())->put($path, $image['contents'], ['visibility' => 'public']);

            return $path;
        } catch (BusinessException $e) {
            Log::debug('Resource image skipped', ['resource' => $resource->cuid, 'image' => $name, 'reason' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($source);
        }
    }

    private static function disk(): string
    {
        $disk = config('filesystems.uploads_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }
}
