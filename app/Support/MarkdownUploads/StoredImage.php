<?php

namespace App\Support\MarkdownUploads;

use App\Models\Upload;

/**
 * What the renderer knows about an uploaded image (#1207, ADR 0040): its real size, and the smaller versions that exist.
 */
final readonly class StoredImage
{
    /**
     * `sizes` of a tile of a gallery of two images or more: two columns of 21rem at most
     */
    public const string SIZES_TILE = '(min-width: 42rem) 21rem, 50vw';

    /**
     * `sizes` of one image: a gallery of one, or an image inside a sentence
     */
    public const string SIZES_SINGLE = '(min-width: 48rem) 48rem, 100vw';

    /**
     * @param  list<string>  $candidates  The srcset entries, from the smallest: "url 640w"
     */
    private function __construct(
        public int $width,
        public int $height,
        private array $candidates,
    ) {}

    public static function of(Upload $upload): self
    {
        $candidates = [];
        foreach ($upload->sizes ?? [] as $width) {
            $candidates[] = $upload->sizeUrl($width).' '.$width.'w';
        }

        return new self($upload->width, $upload->height, $candidates === [] ? [] : [...$candidates, $upload->url().' '.$upload->width.'w']);
    }

    /**
     * The attributes of the `<img>`: the size always (the browser reserves the place before the image loads), and
     * `srcset` with `sizes` when there is a smaller version to choose.
     *
     * @param  string  $sizes  One of the SIZES_ constants
     * @return array<string, string>
     */
    public function attributes(string $sizes): array
    {
        $attributes = ['width' => (string) $this->width, 'height' => (string) $this->height];

        if ($this->candidates !== []) {
            $attributes['srcset'] = implode(', ', $this->candidates);
            $attributes['sizes'] = $sizes;
        }

        return $attributes;
    }
}
