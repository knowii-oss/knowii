<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use stdClass;

/**
 * The legal pages (#902, #903, #904, #77): one Markdown file per page, rendered on the server (ADR 0013).
 *
 * - The texts of an instance live only in the folder of `config('legal.path')`, `storage/app/legal/` (#1653, ADR 0055):
 *   `<file>.md` for each page of PAGES, and `subprocessors.json` for the sub-processors. The code holds no text. A page
 *   without a file does not exist: its address answers 404, and what links to it is hidden (exists(), available()).
 * - The repository holds neutral templates for an operator, which this class never reads and nothing serves. The texts of
 *   the hosted instance are in `deploy/`, and its deploy copies them (SELF_HOSTING.md, "Legal pages").
 * - A file starts with a front matter block: `title`, `updated` (YYYY-MM-DD) and `draft` (true until the text is
 *   approved: the page then shows a draft notice).
 * - `{{retention.<period>}}` shows a retention period of App\Support\Retention (ADR 0030) and `{{subprocessors}}`
 *   the table of `subprocessors.json`, so the text never drifts from the code. `{{subprocessors.community}}` shows only
 *   the sub-processors that receive the data of a community: the list of the DPA (#1352). `{{sign_in_provider}}` shows
 *   the label of the sign-in provider (#1643). `{{cookies.session}}` shows the name of the session cookie of the
 *   instance (#1342). `{{operator}}`, `{{contact}}` and `{{site}}` show who runs the instance, its contact address and
 *   the host of its address (#1653).
 */
final class LegalPages
{
    /**
     * Whose data a sub-processor receives (#1352): the accounts and the public forms, or the communities
     */
    public const string SCOPE_PLATFORM = 'platform';

    public const string SCOPE_COMMUNITY = 'community';

    /**
     * The pages, by route name prefix, with the name of their Markdown file. WARNING: aligned with LEGAL_PAGE_SLUGS
     * (libs/common/src/lib/constants.ts)
     */
    public const array PAGES = [
        'privacy-policy' => 'policy',
        'terms-of-service' => 'terms',
        'dpa' => 'dpa',
        'imprint' => 'imprint',
        'content-policy' => 'content-policy',
        'security' => 'security',
    ];

    /**
     * The file of the sub-processors, in the folder of the texts
     */
    public const string SUBPROCESSORS_FILE = 'subprocessors.json';

    /**
     * Whether the instance has the text of a page. Read at each call: a file that is copied later exists at once.
     */
    public static function exists(string $page): bool
    {
        $file = self::PAGES[$page] ?? null;

        return $file !== null && File::isFile(self::path($file));
    }

    /**
     * The pages that the instance has a text for, in the order of PAGES
     *
     * @return list<string>
     */
    public static function available(): array
    {
        return array_values(array_filter(array_keys(self::PAGES), self::exists(...)));
    }

    /**
     * The rendered text of a page. The caller checks exists() first: a page without a file has nothing to render.
     *
     * @return array{title: string, updated: ?string, draft: bool, html: string}
     */
    public static function render(string $page): array
    {
        $file = self::PAGES[$page] ?? throw new InvalidArgumentException("Unknown legal page: {$page}");
        [$meta, $markdown] = self::frontMatter(self::read(self::path($file)));

        return [
            'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $page)),
            // The same check as updated(): a mark of a template ("[TODO: ...]") is not shown as a date
            'updated' => self::date($meta['updated'] ?? ''),
            'draft' => in_array(strtolower($meta['draft'] ?? 'false'), ['true', 'yes', '1'], true),
            'html' => Markdown::render(self::fillPlaceholders($markdown)),
        ];
    }

    /**
     * The `updated` date (YYYY-MM-DD) of a page, the version of its text (#1353). Null when the instance has no file for
     * the page, when the file gives no date, or when the value is not a date written YYYY-MM-DD.
     */
    public static function updated(string $page): ?string
    {
        $file = self::PAGES[$page] ?? throw new InvalidArgumentException("Unknown legal page: {$page}");
        $path = self::path($file);
        if (! File::isFile($path)) {
            return null;
        }

        return self::date(self::frontMatter(self::read($path))[0]['updated'] ?? '');
    }

    /**
     * A date written YYYY-MM-DD that exists, else null
     */
    private static function date(string $value): ?string
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $date) !== 1) {
            return null;
        }

        return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? $value : null;
    }

    /**
     * The text of a file of the operator, as valid UTF-8 and without a byte order mark: an editor that adds the mark, or
     * a file in another encoding, must not hide the front matter (the draft notice) or break the page
     */
    private static function read(string $path): string
    {
        $contents = File::get($path);
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        return mb_scrub($contents, 'UTF-8');
    }

    /**
     * Replaces the placeholders. An unknown placeholder stays visible, so that the operator sees it on the page.
     */
    public static function fillPlaceholders(string $markdown): string
    {
        $sessionCookie = config('session.cookie');
        $appUrl = config('app.url');
        $site = is_string($appUrl) ? parse_url($appUrl, PHP_URL_HOST) : null;
        $values = [
            'cookies.session' => is_string($sessionCookie) ? $sessionCookie : '',
            // The name of the sign-in provider of this instance (#1643), or a plain statement when there is none
            'sign_in_provider' => OpenIdConnect::enabled() ? OpenIdConnect::label() : 'none on this site',
            // The values of the instance (#1653): who runs it, its contact address, and the host of its address
            'operator' => InstanceValues::operator(),
            'contact' => InstanceValues::contactEmail(),
            'site' => is_string($site) ? $site : '',
        ];
        foreach (Retention::periods() as $key => $value) {
            $values['retention.'.$key] = $value;
        }

        // The file of the sub-processors is read once, and only for a text that shows them
        $entries = null;
        $tables = ['subprocessors' => null, 'subprocessors.community' => self::SCOPE_COMMUNITY];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/',
            static function (array $match) use ($values, $tables, &$entries): string {
                if (array_key_exists($match[1], $tables)) {
                    $entries ??= self::subprocessorEntries();

                    return self::table(self::inScope($entries, $tables[$match[1]]));
                }

                return $values[$match[1]] ?? $match[0];
            },
            $markdown
        );
    }

    /**
     * The sub-processors of the instance as a Markdown table: all of them, or those of one scope
     */
    public static function subprocessorTable(?string $scope = null): string
    {
        return self::table(self::subprocessors($scope));
    }

    /**
     * The sub-processors of `subprocessors.json`, or those that receive the data of one scope (SCOPE_PLATFORM,
     * SCOPE_COMMUNITY). An entry without a scope is in every scope: a list never hides a sub-processor by mistake. The
     * list is empty when the instance has no file, and when the file is not a JSON list (with a warning in the log).
     *
     * @return list<array{name: string, country: string, purpose: string, safeguard: string}>
     */
    public static function subprocessors(?string $scope = null): array
    {
        return self::inScope(self::subprocessorEntries(), $scope);
    }

    /**
     * The entries of `subprocessors.json`: a JSON list of objects with `name`, `country`, `purpose`, `safeguard`, `scope`
     * (a list of scopes) and, for a sub-processor that only an optional feature uses, `feature`. An entry that is not an
     * object is left out. A value that is not text is empty.
     *
     * @return list<array{name: string, country: string, purpose: string, safeguard: string, feature: string, scopes: ?list<string>}>
     */
    private static function subprocessorEntries(): array
    {
        $path = self::folder().'/'.self::SUBPROCESSORS_FILE;
        if (! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode(self::read($path));
        if (! is_array($decoded)) {
            Log::warning('Legal pages: the file of the sub-processors is left out because it is not a JSON list', ['file' => $path]);

            return [];
        }

        $entries = [];
        foreach ($decoded as $entry) {
            if (! $entry instanceof stdClass) {
                continue;
            }
            $fields = get_object_vars($entry);
            $scopes = $fields['scope'] ?? null;
            $entries[] = [
                'name' => self::text($fields['name'] ?? null),
                'country' => self::text($fields['country'] ?? null),
                'purpose' => self::text($fields['purpose'] ?? null),
                'safeguard' => self::text($fields['safeguard'] ?? null),
                'feature' => self::text($fields['feature'] ?? null),
                'scopes' => is_array($scopes) ? array_values(array_filter($scopes, is_string(...))) : null,
            ];
        }

        return $entries;
    }

    /**
     * The entries of one scope (all of them without a scope), with the sentence of an optional feature in the purpose
     *
     * @param  list<array{name: string, country: string, purpose: string, safeguard: string, feature: string, scopes: ?list<string>}>  $entries
     * @return list<array{name: string, country: string, purpose: string, safeguard: string}>
     */
    private static function inScope(array $entries, ?string $scope): array
    {
        $list = [];
        foreach ($entries as $entry) {
            if ($scope !== null && $entry['scopes'] !== null && ! in_array($scope, $entry['scopes'], true)) {
                continue;
            }
            // A sub-processor of an optional feature receives data only from the communities that turn it on
            $list[] = [
                'name' => $entry['name'],
                'country' => $entry['country'],
                'purpose' => $entry['feature'] === '' ? $entry['purpose'] : rtrim($entry['purpose'], '.').'. Only for the communities whose owner turns on '.$entry['feature'].'.',
                'safeguard' => $entry['safeguard'],
            ];
        }

        return $list;
    }

    /**
     * @param  list<array{name: string, country: string, purpose: string, safeguard: string}>  $subprocessors
     */
    private static function table(array $subprocessors): string
    {
        $rows = ['| Sub-processor | Country | Purpose | Transfer safeguard |', '| --- | --- | --- | --- |'];
        foreach ($subprocessors as $subprocessor) {
            $rows[] = '| '.implode(' | ', array_map(
                static fn (string $cell): string => str_replace('|', '\|', $cell),
                [$subprocessor['name'], $subprocessor['country'], $subprocessor['purpose'], $subprocessor['safeguard']]
            )).' |';
        }

        return implode("\n", $rows);
    }

    /**
     * The folder of the texts of the instance: `config('legal.path')`, and no other place
     */
    private static function folder(): string
    {
        $folder = config('legal.path');

        return is_string($folder) ? rtrim($folder, '/') : '';
    }

    private static function path(string $file): string
    {
        return self::folder()."/{$file}.md";
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
                // A comment after the value ("draft: true # until the lawyer read it") is not part of it
                $value = preg_replace('/\s+#.*$/', '', $field[2]) ?? $field[2];
                $meta[$field[1]] = trim($value, " \t\"'");
            }
        }

        return [$meta, $match[2]];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
