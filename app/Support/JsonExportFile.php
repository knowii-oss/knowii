<?php

namespace App\Support;

use RuntimeException;

/**
 * Writes a JSON file for a community export (#1353): UTF-8, pretty printed, `format_version` first. The lists are
 * written one item at a time, so an export never holds all the posts or messages of a community in memory.
 *
 * @phpstan-type Row1 array<array-key, scalar|null>
 * @phpstan-type Row2 array<array-key, scalar|null|Row1>
 * @phpstan-type Row3 array<array-key, scalar|null|Row2>
 * @phpstan-type Row4 array<array-key, scalar|null|Row3>
 * @phpstan-type Row array<array-key, scalar|null|Row4>
 */
final class JsonExportFile
{
    public const int FORMAT_VERSION = 1;

    private const int FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /**
     * @param  array<string, Row>  $head  single values, written before the lists
     * @param  array<string, iterable<Row>>  $lists  the items are read and written one by one
     */
    public static function write(string $path, array $head, array $lists): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Cannot write the export file');
        }

        try {
            self::put($handle, "{\n    \"format_version\": ".self::FORMAT_VERSION);

            foreach ($head as $key => $value) {
                self::put($handle, ",\n    ".self::encode($key).': '.self::encode($value, 1));
            }

            foreach ($lists as $key => $items) {
                self::put($handle, ",\n    ".self::encode($key).': [');
                $empty = true;
                foreach ($items as $item) {
                    self::put($handle, ($empty ? "\n" : ",\n").'        '.self::encode($item, 2));
                    $empty = false;
                }
                self::put($handle, $empty ? ']' : "\n    ]");
            }

            self::put($handle, "\n}\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private static function put($handle, string $text): void
    {
        if (fwrite($handle, $text) === false) {
            throw new RuntimeException('Cannot write the export file');
        }
    }

    /**
     * @param  string|Row  $value
     */
    private static function encode(string|array $value, int $level = 0): string
    {
        return str_replace("\n", "\n".str_repeat('    ', $level), json_encode($value, self::FLAGS));
    }
}
