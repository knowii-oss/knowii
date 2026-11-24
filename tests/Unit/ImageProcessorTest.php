<?php

use App\Constants;
use App\Exceptions\BusinessException;
use App\Support\ImageProcessor;

/**
 * A JPEG file of this size, wider than high, with this EXIF orientation
 */
function orientedJpeg(int $width, int $height, int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 200, 40, 90));
    ob_start();
    imagejpeg($image, null, 80);
    $jpeg = (string) ob_get_clean();
    unset($image);

    // An APP1 segment with a little-endian TIFF header and one IFD entry: Orientation (0x0112), SHORT, 1, the value
    $tiff = "II*\0".pack('V', 8).pack('v', 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0);
    $payload = "Exif\0\0".$tiff;
    $path = (string) tempnam(sys_get_temp_dir(), 'knowii-jpeg-');
    file_put_contents($path, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));

    return $path;
}

test('a photo with a rotation in its EXIF data comes out upright and scaled down', function (int $orientation, bool $turned) {
    $path = orientedJpeg(3000, 2000, $orientation);

    try {
        $result = ImageProcessor::process($path);
    } finally {
        @unlink($path);
    }

    $largest = Constants::MAX_UPLOAD_IMAGE_DIMENSION;
    $smallest = (int) round(2000 * $largest / 3000);
    expect([$result['width'], $result['height']])->toBe($turned ? [$smallest, $largest] : [$largest, $smallest]);
})->with([
    'no rotation' => [1, false],
    'a quarter turn' => [6, true],
    'a half turn' => [3, false],
    'a quarter turn the other way' => [8, true],
]);

test('an image with too many pixels is refused from its header, before it is decoded', function () {
    // A PNG header of 10 000 x 5 000 pixels and nothing else: decoding it would fail, the header check comes first
    $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 10000, 5000)."\x08\x02\0\0\0";
    $path = (string) tempnam(sys_get_temp_dir(), 'knowii-png-');
    file_put_contents($path, $header.pack('N', crc32(substr($header, 12))));

    try {
        expect(fn () => ImageProcessor::process($path))->toThrow(BusinessException::class, 'This image is too large: at most 25 megapixels.');
    } finally {
        @unlink($path);
    }
});
