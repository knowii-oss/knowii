<?php

namespace App\Support;

use App\Constants;
use App\Exceptions\BusinessException;
use App\Models\CommunityResource;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The images of a captured article (ADR 0018, #1247): downloaded through SafeUrl, re-encoded and stored by Knowii
 * under `community-resources/{cuid}/images/`, so readers never call the curated site. The Markdown points to the
 * stored copies. An image that cannot be stored, and every image after the first CAPTURE_MAX_IMAGES, becomes its
 * alternative text.
 */
final class ArticleImages
{
    /**
     * `![alt](url)` and `![alt](url "title")`, also with the URL between angle brackets
     */
    private const string IMAGE_PATTERN = '/!\[((?:[^\]\\\\]|\\\\.)*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/';

    /**
     * @param  Closure(string): ?string  $download  The bytes of an image, or null (FetchUrl::downloadImage)
     */
    public static function store(CommunityResource $communityResource, string $markdown, Closure $download): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(CommunityResourceFiles::disk());
        $folder = CommunityResourceFiles::folder($communityResource->cuid).'/images';
        // A new capture replaces the images of the previous one
        $disk->deleteDirectory($folder);

        /** @var array<string, string|null> $stored URL of the page => URL of the stored copy (null: not stored) */
        $stored = [];

        $result = preg_replace_callback(self::IMAGE_PATTERN, static function (array $match) use (&$stored, $download, $disk, $folder): string {
            $alt = trim(str_replace(['\\[', '\\]'], ['[', ']'], $match[1]));
            $url = $match[2];

            if (! array_key_exists($url, $stored)) {
                // At most CAPTURE_MAX_IMAGES stored, and as many failed tries
                $stored[$url] = count(array_filter($stored)) < Constants::CAPTURE_MAX_IMAGES && count($stored) < 2 * Constants::CAPTURE_MAX_IMAGES
                    ? self::storeOne($url, count($stored) + 1, $download, $disk, $folder)
                    : null;
            }

            $copy = $stored[$url];

            return $copy === null ? $alt : '!['.$match[1].']('.$copy.')';
        }, $markdown);

        Log::info('Article images stored', ['resource' => $communityResource->cuid, 'images' => count(array_filter($stored)), 'skipped' => count($stored) - count(array_filter($stored))]);

        return $result ?? $markdown;
    }

    /**
     * @param  Closure(string): ?string  $download
     */
    private static function storeOne(string $url, int $number, Closure $download, FilesystemAdapter $disk, string $folder): ?string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            // data: URIs and relative paths that the capture could not resolve
            return null;
        }

        $bytes = $download($url);
        if ($bytes === null) {
            return null;
        }

        $source = tempnam(sys_get_temp_dir(), 'knowii-article-image-');
        if ($source === false) {
            return null;
        }

        try {
            file_put_contents($source, $bytes);
            $image = ImageProcessor::process($source);
            $path = sprintf('%s/%d.%s', $folder, $number, $image['extension']);
            $disk->put($path, $image['contents'], ['visibility' => 'public']);

            return $disk->url($path);
        } catch (BusinessException $e) {
            Log::debug('Article image skipped', ['reason' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($source);
        }
    }
}
