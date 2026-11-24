<?php

namespace App\Support;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * The body of a fetched answer, as the HTTP client writes it: the decoded bytes. A write that would pass the limit is
 * refused (it writes nothing and returns 0), so curl stops the transfer at once, and {@see self::isTooLarge()} tells
 * why.
 */
final class SizeLimitedStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $written = 0;

    private bool $tooLarge = false;

    public function __construct(private readonly int $maxBytes)
    {
        $this->stream = Utils::streamFor(Utils::tryFopen('php://temp', 'w+'));
    }

    public function write(string $string): int
    {
        if ($this->tooLarge || $this->written + strlen($string) > $this->maxBytes) {
            $this->tooLarge = true;

            return 0;
        }

        $this->written += strlen($string);

        return $this->stream->write($string);
    }

    public function isTooLarge(): bool
    {
        return $this->tooLarge;
    }
}
