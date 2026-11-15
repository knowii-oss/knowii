<?php

namespace App\Support\MarkdownUploads;

use App\Models\Upload;
use App\Support\Markdown;
use App\Support\UploadReferences;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Gives the images that are uploads of this instance their size and their smaller versions (#1207, ADR 0040).
 *
 * After parsing, one query finds the uploads of all the images of the text. An image gets its attributes only when its
 * address is exactly the address of the stored file, so a member cannot borrow the attributes of an upload for another
 * address. The renderers (`ImageGalleryRenderer` and `InlineImageRenderer`) write the attributes.
 */
final class UploadImagesExtension implements ExtensionInterface
{
    /**
     * The key of the data of an image node
     */
    public const string DATA_KEY = 'stored_image';

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'])
            // Before the image renderer of CommonMark, which it calls
            ->addRenderer(Image::class, new InlineImageRenderer, 10);
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        if (! Markdown::imageSizesEnabled()) {
            return;
        }

        /** @var list<array{0: Image, 1: list<string>}> $images */
        $images = [];
        $cuids = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image) {
                $found = UploadReferences::cuidsIn($node->getUrl());
                if ($found !== []) {
                    $images[] = [$node, $found];
                    array_push($cuids, ...$found);
                }
            }
        }

        if ($images === []) {
            return;
        }

        // One query for all the images of the text
        $uploads = [];
        foreach (Upload::query()->whereIn('cuid', array_values(array_unique($cuids)))->get(['cuid', 'disk', 'path', 'width', 'height', 'sizes']) as $upload) {
            $uploads[$upload->cuid] = $upload;
        }

        foreach ($images as [$image, $found]) {
            foreach ($found as $cuid) {
                $upload = $uploads[$cuid] ?? null;
                if ($upload !== null && self::isAddressOf($image->getUrl(), $upload)) {
                    $image->data->set(self::DATA_KEY, StoredImage::of($upload));

                    break;
                }
            }
        }
    }

    /**
     * What the renderer knows about an image, if it is an upload
     */
    public static function storedImage(Image $image): ?StoredImage
    {
        if (! $image->data->has(self::DATA_KEY)) {
            return null;
        }

        $stored = $image->data->get(self::DATA_KEY);

        return $stored instanceof StoredImage ? $stored : null;
    }

    /**
     * Whether the address is the address of the file of the upload: the same path, on this instance or on the host
     * of the storage, without query.
     */
    private static function isAddressOf(string $address, Upload $upload): bool
    {
        $given = parse_url($address);
        $own = parse_url($upload->url());
        if (! is_array($given) || ! is_array($own)) {
            return false;
        }

        $path = $given['path'] ?? null;
        if ($path === null || $path !== ($own['path'] ?? null) || isset($given['query']) || isset($given['fragment'])) {
            return false;
        }

        // A relative address belongs to this instance
        $host = strtolower($given['host'] ?? '');
        if ($host === '') {
            return true;
        }

        if (! in_array(strtolower($given['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $appUrl = config('app.url');
        $appHost = is_string($appUrl) ? parse_url($appUrl, PHP_URL_HOST) : null;

        return $host === strtolower($own['host'] ?? '') || (is_string($appHost) && $host === strtolower($appHost));
    }
}
