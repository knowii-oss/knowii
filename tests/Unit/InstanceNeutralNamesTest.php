<?php

/*
 * The code of the platform depends on no service of one instance (#1643). The external account service is a contract
 * (documentation/architecture/external-account-service.md) plus configuration: the name of the account product of one
 * instance and the hosts of its services must not come back in the code, the configuration, the routes, the front-end,
 * the shared library or the tests. Use "the external account service" (or "the sign-in provider"), and in test data the
 * label "Example account" and example.com hosts.
 *
 * The patterns are regular expressions, so this file does not match itself.
 */

/**
 * The names and the hosts of one instance
 *
 * @return array<string, string>
 */
function instanceValuePatterns(): array
{
    return [
        'the account product of one instance' => '/knowii[\s*\/#]+account/i',
        'the account site of one instance' => '/\bmy\.knowii\.net\b/i',
        'the sign-in host of one instance' => '/\bauth\.knowii\.net\b/i',
    ];
}

/**
 * The folders that the guard reads
 *
 * @return list<string>
 */
function instanceNeutralFolders(): array
{
    return ['app', 'config', 'routes', 'apps/knowii/src', 'libs/common/src', 'tests'];
}

/**
 * The files that still hold one of the values, with the reason and the issue. This list can only shrink.
 *
 * @return array<string, string>
 */
function instanceValueExceptions(): array
{
    // None since the legal words became neutral (#1643). Add a file here only with its issue, and remove it with the fix
    return [];
}

/**
 * The files that the guard never reads: they hold the values of one instance on purpose
 *
 * @return list<string>
 */
function instanceValueSkippedFiles(): array
{
    return [
        // The publisher, the contact address and the links of the footer of one instance: #1653
        'libs/common/src/lib/metadata.json',
    ];
}

/**
 * Each file of the folders that holds a value, with the names of the values that it holds
 *
 * @return array{matches: array<string, list<string>>, read: int}
 */
function instanceValueMatches(): array
{
    $root = dirname(__DIR__, 2);
    $matches = [];
    $read = 0;

    foreach (instanceNeutralFolders() as $folder) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (in_array($path, instanceValueSkippedFiles(), true)) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            // A binary file (an image, a font) holds no text to check
            if (str_contains($source, "\0")) {
                continue;
            }
            $read++;

            foreach (instanceValuePatterns() as $name => $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    $matches[$path][] = $name;
                }
            }
        }
    }
    ksort($matches);

    return ['matches' => $matches, 'read' => $read];
}

test('no name or host of one instance is in the code, the configuration, the routes, the front-end or the tests (#1643)', function () {
    ['matches' => $matches, 'read' => $read] = instanceValueMatches();

    // The guard really read the folders: a wrong path must not pass in silence
    expect($read)->toBeGreaterThan(500)
        ->and(array_diff_key($matches, instanceValueExceptions()))->toBe([]);
});

test('every exception of the guard still holds a value of one instance: remove the ones that do not (#1643)', function () {
    ['matches' => $matches] = instanceValueMatches();

    // Every exception must still hold a match (with no exception, the list of stale ones is empty)
    $stale = array_values(array_filter(array_keys(instanceValueExceptions()), static fn (string $path): bool => ! array_key_exists($path, $matches)));

    expect($stale)->toBe([]);
});

test('the guard finds each value, in any case (#1643)', function (string $text) {
    $found = array_filter(instanceValuePatterns(), static fn (string $pattern): bool => preg_match($pattern, $text) === 1);

    expect($found)->not->toBe([]);
})->with([
    'the product name' => ['Continue with '.'Knowii'.' account'],
    'the product name in lower case' => ['the '.'knowii'.' account of a person'],
    'the product name over a line break' => ['the '.'Knowii'."\n * account"],
    'the account site' => ['https://my.'.'knowii.net/account'],
    'the sign-in host in upper case' => ['https://AUTH.'.'KNOWII.NET'],
]);

test('the guard leaves neutral names alone (#1643)', function (string $text) {
    $found = array_filter(instanceValuePatterns(), static fn (string $pattern): bool => preg_match($pattern, $text) === 1);

    expect($found)->toBe([]);
})->with([
    'the test label' => ['Continue with Example account'],
    'an enum of the platform' => ['KnowiiAccountDeletionProof::SignIn'],
    'a neutral host' => ['https://accounts.example.com'],
]);
