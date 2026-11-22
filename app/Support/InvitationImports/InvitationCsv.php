<?php

namespace App\Support\InvitationImports;

use App\Constants;
use App\Enums\KnowiiInvitationImportRefusal;

/**
 * Reads a CSV file of invitations (#1390, ADR 0061). No query and no community: the checks of the rows come after.
 *
 * - UTF-8 only: a byte order mark at the start is removed; bytes that are not UTF-8, or a NUL byte, refuse the file.
 *   No guess of another encoding.
 * - CRLF, LF and a lone CR end a record. The delimiter is a comma, or a semicolon when the header holds no comma and
 *   holds a semicolon (some spreadsheets write it). Quotes follow RFC 4180: a quoted cell holds the delimiter, a line
 *   break and `""`. A quote opens a quoted cell only as the first character of a cell, else it is a plain character. A
 *   quote that is still open at the end of the file refuses the file. No escape character.
 * - The header is the first record that is not empty. A header is trimmed and read without the case, a space or a dash
 *   read as `_`: `email` (required), `name` (read and dropped here: it never leaves the class), `role`,
 *   `access_groups`, `tags`. Another column is ignored, and kept by name for the preview. A known column twice, or more
 *   than Constants::MAX_INVITATION_IMPORT_COLUMNS columns, refuse the file.
 * - A record whose cells are all empty is skipped and not counted; more than Constants::MAX_INVITATION_IMPORT_ROWS other
 *   records refuse the file. Each cell is trimmed. A record with more cells than the header: the extra cells are
 *   ignored when they are empty, else the record is refused (`extra_cells`). A cell over
 *   Constants::MAX_INVITATION_IMPORT_CELL_LENGTH characters refuses its record (`cell_too_long`).
 */
final class InvitationCsv
{
    public const string TOO_LARGE = 'The file is larger than 1 MB';

    public const string NOT_UTF8 = 'Save the file as CSV UTF-8, then upload it again';

    public const string NO_EMAIL_COLUMN = 'The file needs a column named email';

    public const string TOO_MANY_COLUMNS = 'A file holds at most 20 columns';

    public const string TOO_MANY_ROWS = 'A file holds at most 1000 rows. Split it into several files.';

    public const string OPEN_QUOTE = 'A cell of the file opens a quote that is never closed. Close the quote, then upload the file again.';

    /**
     * The columns that the import reads
     */
    public const array COLUMNS = ['email', 'name', 'role', 'access_groups', 'tags'];

    private const string BOM = "\xEF\xBB\xBF";

    private const int MAX_IGNORED_COLUMN_LENGTH = 64;

    public static function read(string $path): InvitationCsvResult
    {
        $size = @filesize($path);
        if ($size === false || $size > Constants::MAX_INVITATION_IMPORT_KILOBYTES * 1024) {
            return InvitationCsvResult::failed(self::TOO_LARGE);
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return InvitationCsvResult::failed(self::NOT_UTF8);
        }

        return self::parse($content);
    }

    /**
     * The same reading for the content of a file
     */
    public static function parse(string $content): InvitationCsvResult
    {
        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, strlen(self::BOM));
        }
        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            return InvitationCsvResult::failed(self::NOT_UTF8);
        }

        [$records, $closed] = self::records($content, ',');
        $headerIndex = self::firstNonEmpty($records);
        if ($headerIndex === null) {
            return InvitationCsvResult::failed($closed ? self::NO_EMAIL_COLUMN : self::OPEN_QUOTE);
        }
        $header = $records[$headerIndex];
        if (count($header) === 1 && str_contains($header[0], ';')) {
            [$records, $closed] = self::records($content, ';');
            $headerIndex = self::firstNonEmpty($records) ?? 0;
            $header = $records[$headerIndex];
        }
        if (! $closed) {
            return InvitationCsvResult::failed(self::OPEN_QUOTE);
        }

        if (count($header) > Constants::MAX_INVITATION_IMPORT_COLUMNS) {
            return InvitationCsvResult::failed(self::TOO_MANY_COLUMNS);
        }

        /** @var array<string, int> $columns */
        $columns = [];
        $ignored = [];
        foreach ($header as $index => $cell) {
            $name = self::columnName($cell);
            if (in_array($name, self::COLUMNS, true)) {
                if (array_key_exists($name, $columns)) {
                    return InvitationCsvResult::failed('The column '.$name.' is in the file twice');
                }
                $columns[$name] = $index;
            } elseif (trim($cell) !== '') {
                $ignored[] = mb_substr(trim($cell), 0, self::MAX_IGNORED_COLUMN_LENGTH);
            }
        }
        if (! array_key_exists('email', $columns)) {
            return InvitationCsvResult::failed(self::NO_EMAIL_COLUMN);
        }

        $width = count($header);
        $result = [];
        $total = count($records);
        for ($index = $headerIndex + 1; $index < $total; $index++) {
            $cells = array_map(trim(...), $records[$index]);
            if (implode('', $cells) === '') {
                continue;
            }
            if (count($result) >= Constants::MAX_INVITATION_IMPORT_ROWS) {
                return InvitationCsvResult::failed(self::TOO_MANY_ROWS);
            }

            $problem = null;
            if (implode('', array_slice($cells, $width)) !== '') {
                $problem = KnowiiInvitationImportRefusal::ExtraCells;
            } else {
                foreach (array_slice($cells, 0, $width) as $cell) {
                    if (mb_strlen($cell) > Constants::MAX_INVITATION_IMPORT_CELL_LENGTH) {
                        $problem = KnowiiInvitationImportRefusal::CellTooLong;
                        break;
                    }
                }
            }

            $cell = static fn (string $name): string => array_key_exists($name, $columns) ? ($cells[$columns[$name]] ?? '') : '';
            $result[] = new InvitationCsvRecord(
                rowNumber: $index - $headerIndex + 1,
                email: $cell('email'),
                role: $cell('role'),
                accessGroups: $cell('access_groups'),
                tags: $cell('tags'),
                problem: $problem,
            );
        }

        return InvitationCsvResult::read(array_slice($ignored, 0, Constants::MAX_INVITATION_IMPORT_COLUMNS), $result);
    }

    /**
     * "Access groups", "access-groups" and "ACCESS_GROUPS" all name `access_groups`
     */
    public static function columnName(string $header): string
    {
        return (string) preg_replace('/[\s-]/u', '_', mb_strtolower(trim($header)));
    }

    /**
     * The records of the content, each a list of cells (not trimmed), and whether every quoted cell was closed. RFC 4180
     * with a lone CR as a line break too. A quote opens a quoted cell only as the first character of a cell; anywhere
     * else it is a plain character. The delimiter, the quote and the line breaks are ASCII bytes, which never occur
     * inside a multibyte UTF-8 character: the reading goes byte by byte.
     *
     * @return array{0: list<list<string>>, 1: bool}
     */
    private static function records(string $content, string $delimiter): array
    {
        $records = [];
        $record = [];
        $cell = '';
        $quoted = false;
        $cellStart = true;
        $length = strlen($content);

        for ($at = 0; $at < $length; $at++) {
            $char = $content[$at];
            if ($quoted) {
                if ($char === '"') {
                    if ($at + 1 < $length && $content[$at + 1] === '"') {
                        $cell .= '"';
                        $at++;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $cell .= $char;
                }

                continue;
            }

            if ($char === '"' && $cellStart) {
                $quoted = true;
                $cellStart = false;
            } elseif ($char === $delimiter) {
                $record[] = $cell;
                $cell = '';
                $cellStart = true;
            } elseif ($char === "\r" || $char === "\n") {
                if ($char === "\r" && $at + 1 < $length && $content[$at + 1] === "\n") {
                    $at++;
                }
                $record[] = $cell;
                $records[] = $record;
                $record = [];
                $cell = '';
                $cellStart = true;
            } else {
                $cell .= $char;
                $cellStart = false;
            }
        }

        // The last record has no line break after it
        if ($cell !== '' || $record !== [] || ! $cellStart) {
            $record[] = $cell;
            $records[] = $record;
        }

        return [$records, ! $quoted];
    }

    /**
     * @param  list<list<string>>  $records
     */
    private static function firstNonEmpty(array $records): ?int
    {
        foreach ($records as $index => $record) {
            if (trim(implode('', $record)) !== '') {
                return $index;
            }
        }

        return null;
    }
}
