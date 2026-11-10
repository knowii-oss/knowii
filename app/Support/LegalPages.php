<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * The legal pages (#902, #903, #904, #77): one Markdown file per page, rendered on the server (ADR 0013).
 *
 * - `resources/markdown/<page>.md` is the text of the Knowii instance. A self-hosted instance replaces a page with
 *   `storage/app/legal/<page>.md`.
 * - A file starts with a front matter block: `title`, `updated` (YYYY-MM-DD) and `draft` (true until the text is
 *   approved: the page then shows a draft notice).
 * - `{{retention.<period>}}` shows a retention period of App\Support\Retention (ADR 0030) and `{{subprocessors}}`
 *   the table of config/legal.php, so the text never drifts from the code.
 */
final class LegalPages
{
    /**
     * The pages, by route name prefix, with the name of their Markdown file
     */
    public const array PAGES = [
        'privacy-policy' => 'policy',
        'terms-of-service' => 'terms',
    ];

    /**
     * @return array{title: string, updated: ?string, draft: bool, html: string}
     */
    public static function render(string $page): array
    {
        $file = self::PAGES[$page] ?? throw new InvalidArgumentException("Unknown legal page: {$page}");
        $override = storage_path("app/legal/{$file}.md");
        $path = File::exists($override) ? $override : resource_path("markdown/{$file}.md");

        [$meta, $markdown] = self::frontMatter(File::get($path));

        return [
            'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $page)),
            'updated' => $meta['updated'] ?? null,
            'draft' => in_array(strtolower($meta['draft'] ?? 'false'), ['true', 'yes', '1'], true),
            'html' => Markdown::render(self::fillPlaceholders($markdown)),
        ];
    }

    /**
     * Replaces the placeholders. An unknown placeholder stays visible, and LegalPagesTest fails on it.
     */
    public static function fillPlaceholders(string $markdown): string
    {
        $values = ['subprocessors' => self::subprocessorTable()];
        foreach (Retention::periods() as $key => $value) {
            $values['retention.'.$key] = $value;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/',
            static fn (array $match): string => $values[$match[1]] ?? $match[0],
            $markdown
        );
    }

    /**
     * The sub-processors of config/legal.php as a Markdown table
     */
    public static function subprocessorTable(): string
    {
        $rows = ['| Sub-processor | Country | Purpose | Transfer safeguard |', '| --- | --- | --- | --- |'];
        foreach (self::subprocessors() as $subprocessor) {
            $rows[] = '| '.implode(' | ', array_map(
                static fn (string $cell): string => str_replace('|', '\|', $cell),
                [$subprocessor['name'], $subprocessor['country'], $subprocessor['purpose'], $subprocessor['safeguard']]
            )).' |';
        }

        return implode("\n", $rows);
    }

    /**
     * @return list<array{name: string, country: string, purpose: string, safeguard: string}>
     */
    public static function subprocessors(): array
    {
        $configured = config('legal.subprocessors');
        $list = [];
        foreach (is_array($configured) ? $configured : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $list[] = [
                'name' => self::text($entry['name'] ?? null),
                'country' => self::text($entry['country'] ?? null),
                'purpose' => self::text($entry['purpose'] ?? null),
                'safeguard' => self::text($entry['safeguard'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private static function frontMatter(string $contents): array
    {
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $match) !== 1) {
            return [[], $contents];
        }

        $meta = [];
        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $field) === 1) {
                $meta[$field[1]] = trim($field[2], " \t\"'");
            }
        }

        return [$meta, $match[2]];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
