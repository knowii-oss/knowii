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
 *   the table of config/legal.php, so the text never drifts from the code. `{{subprocessors.community}}` shows only
 *   the sub-processors that receive the data of a community: the list of the DPA (#1352). `{{sign_in_provider}}` shows the label of the sign-in provider (#1643). `{{cookies.session}}` shows the name of
 *   the session cookie of the instance (#1342).
 */
final class LegalPages
{
    /**
     * The pages, by route name prefix, with the name of their Markdown file
     */
    /**
     * Whose data a sub-processor receives (#1352): the accounts and the public forms, or the communities
     */
    public const string SCOPE_PLATFORM = 'platform';

    public const string SCOPE_COMMUNITY = 'community';

    public const array PAGES = [
        'privacy-policy' => 'policy',
        'terms-of-service' => 'terms',
        'dpa' => 'dpa',
        'imprint' => 'imprint',
        'content-policy' => 'content-policy',
        'security' => 'security',
    ];

    /**
     * @return array{title: string, updated: ?string, draft: bool, html: string}
     */
    public static function render(string $page): array
    {
        $file = self::PAGES[$page] ?? throw new InvalidArgumentException("Unknown legal page: {$page}");
        [$meta, $markdown] = self::frontMatter(File::get(self::path($file)));

        return [
            'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $page)),
            'updated' => $meta['updated'] ?? null,
            'draft' => in_array(strtolower($meta['draft'] ?? 'false'), ['true', 'yes', '1'], true),
            'html' => Markdown::render(self::fillPlaceholders($markdown)),
        ];
    }

    /**
     * The `updated` date (YYYY-MM-DD) of a page, the version of its text: null when the page has none (#1353).
     */
    public static function updated(string $page): ?string
    {
        $file = self::PAGES[$page] ?? throw new InvalidArgumentException("Unknown legal page: {$page}");

        return self::frontMatter(File::get(self::path($file)))[0]['updated'] ?? null;
    }

    /**
     * Replaces the placeholders. An unknown placeholder stays visible, and LegalPagesTest fails on it.
     */
    public static function fillPlaceholders(string $markdown): string
    {
        $sessionCookie = config('session.cookie');
        $values = [
            'subprocessors' => self::subprocessorTable(),
            'subprocessors.community' => self::subprocessorTable(self::SCOPE_COMMUNITY),
            'cookies.session' => is_string($sessionCookie) ? $sessionCookie : '',
            // The name of the sign-in provider of this instance (#1643), or a plain statement when there is none
            'sign_in_provider' => OpenIdConnect::enabled() ? OpenIdConnect::label() : 'none on this site',
        ];
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
     * The sub-processors of config/legal.php as a Markdown table: all of them, or those of one scope
     */
    public static function subprocessorTable(?string $scope = null): string
    {
        $rows = ['| Sub-processor | Country | Purpose | Transfer safeguard |', '| --- | --- | --- | --- |'];
        foreach (self::subprocessors($scope) as $subprocessor) {
            $rows[] = '| '.implode(' | ', array_map(
                static fn (string $cell): string => str_replace('|', '\|', $cell),
                [$subprocessor['name'], $subprocessor['country'], $subprocessor['purpose'], $subprocessor['safeguard']]
            )).' |';
        }

        return implode("\n", $rows);
    }

    /**
     * The sub-processors, or those that receive the data of one scope (SCOPE_PLATFORM, SCOPE_COMMUNITY). An entry
     * without a scope is in every scope: a list never hides a sub-processor by mistake.
     *
     * @return list<array{name: string, country: string, purpose: string, safeguard: string}>
     */
    public static function subprocessors(?string $scope = null): array
    {
        $configured = config('legal.subprocessors');
        $list = [];
        foreach (is_array($configured) ? $configured : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $scopes = $entry['scope'] ?? null;
            if ($scope !== null && is_array($scopes) && ! in_array($scope, $scopes, true)) {
                continue;
            }
            // A sub-processor of an optional feature receives data only from the communities that turn it on
            $feature = self::text($entry['feature'] ?? null);
            $purpose = self::text($entry['purpose'] ?? null);
            $list[] = [
                'name' => self::text($entry['name'] ?? null),
                'country' => self::text($entry['country'] ?? null),
                'purpose' => $feature === '' ? $purpose : rtrim($purpose, '.').'. Only for the communities whose owner turns on '.$feature.'.',
                'safeguard' => self::text($entry['safeguard'] ?? null),
            ];
        }

        return $list;
    }

    private static function path(string $file): string
    {
        $override = storage_path("app/legal/{$file}.md");

        return File::exists($override) ? $override : resource_path("markdown/{$file}.md");
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
