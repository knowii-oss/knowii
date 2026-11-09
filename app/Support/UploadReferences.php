<?php

namespace App\Support;

/**
 * Finds the uploads (#1178) that a Markdown text uses (#1201).
 *
 * An upload is stored at uploads/{community cuid}/{year}/{month}/{upload cuid}.{extension} (UploadImage). Its URL
 * holds that path, whatever the disk (local storage or S3), so the path is enough to find it in a text: in an image,
 * a link, or a quote of another post.
 */
final class UploadReferences
{
    private const PATTERN = '#uploads/[a-z0-9]+/\d{4}/\d{2}/([a-z0-9]+)\.(?:webp|png|jpe?g|gif)#i';

    /**
     * @return list<string> The cuids of the uploads, without duplicates
     */
    public static function cuidsIn(string $markdown): array
    {
        if (preg_match_all(self::PATTERN, $markdown, $matches) === false) {
            return [];
        }

        return array_values(array_unique(array_map(strtolower(...), $matches[1])));
    }
}
