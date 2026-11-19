<?php

/*
 * The code of the platform depends on no service of one instance (#1643). The external account service is a contract
 * (documentation/architecture/external-account-service.md) plus configuration: the name of the account product of one
 * instance and the hosts of its services must not come back in the code, the configuration, the routes, the front-end,
 * the shared library or the tests. Use "the external account service" (or "the sign-in provider"), and in test data the
 * label "Example account" and example.com hosts.
 *
 * The code names no operator of one instance either (#1653, ADR 0055): the operator, its founder, its company number, its
 * street address and its hosts come from configuration (config/legal.php, config/instance.php). Use "the operator", and
 * in test data "Example Operator", "Example Ltd" and example.com addresses.
 *
 * The guard never reads deploy/: that folder holds the values of the hosted instance on purpose.
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
        // The values of #1653
        'the operator of one instance' => '/develo\s*passion/i',
        'the founder of the operator of one instance' => '/s[eé]bastien/iu',
        'the company number of the operator of one instance' => '/0717[.\s]?561[.\s]?755/',
        'the street address of the operator of one instance' => '/route\s+de\s+mons|\bbinche\b/i',
        'a host of one instance' => '/\bknowii\.net\b/i',
    ];
}

/**
 * The folders that the guard reads. Never deploy/ (the values of the hosted instance), and not yet resources/markdown
 * (the legal texts, until they leave the code: #1653)
 *
 * @return list<string>
 */
function instanceNeutralFolders(): array
{
    return ['app', 'config', 'database', 'lang', 'routes', 'resources/views', 'apps/knowii/src', 'libs/common/src', 'tests'];
}

/**
 * The single files that the guard reads, outside the folders
 *
 * @return list<string>
 */
function instanceNeutralFiles(): array
{
    return ['public/humans.txt', '.env.example', '.env.production.example'];
}

/**
 * The files that still hold one of the values, with the reason and the issue. This list can only shrink.
 *
 * @return array<string, string>
 */
function instanceValueExceptions(): array
{
    // Add a file here only with its issue, and remove it with the fix
    return [
        'tests/Feature/LegalPagesTest.php' => 'It checks the words of the legal texts of the hosted instance, which still live in resources/markdown. The texts and this test move with the second part of #1653',
    ];
}

/**
 * The files that the guard never reads: they hold the values of one instance on purpose
 *
 * @return list<string>
 */
function instanceValueSkippedFiles(): array
{
    // None since the values of one instance come from configuration (#1653)
    return [];
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

    $paths = [];
    foreach (instanceNeutralFolders() as $folder) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
    }
    foreach (instanceNeutralFiles() as $file) {
        // A listed file that is missing must not pass in silence: it counts as a match
        if (! is_file($root.'/'.$file)) {
            $matches[$file][] = 'the file is missing';

            continue;
        }
        $paths[] = $file;
    }

    foreach ($paths as $path) {
        if (in_array($path, instanceValueSkippedFiles(), true)) {
            continue;
        }
        $source = (string) file_get_contents($root.'/'.$path);
        // A binary file (an image, a font) holds no text to check
        if (str_contains($source, "\0")) {
            continue;
        }
        $read++;

        foreach (instanceValuePatterns() as $name => $pattern) {
            // false: the pattern could not read the file (text that is not UTF-8). It counts as a match, never as clean
            if (preg_match($pattern, $source) !== 0) {
                $matches[$path][] = $name;
            }
        }
    }
    ksort($matches);

    return ['matches' => $matches, 'read' => $read];
}

test('no name or host of one instance is in the code, the configuration, the routes, the front-end or the tests (#1643, #1653)', function () {
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

test('the guard never reads the folder of the hosted instance, and skips no file (#1653)', function () {
    $entries = [...instanceNeutralFolders(), ...instanceNeutralFiles(), ...array_keys(instanceValueExceptions()), ...instanceValueSkippedFiles()];

    expect(array_values(array_filter($entries, static fn (string $entry): bool => str_starts_with(ltrim($entry, './'), 'deploy'))))->toBe([])
        ->and(instanceValueSkippedFiles())->toBe([])
        // The legal texts are not read yet, and one test file is the only exception, until the second part of #1653
        ->and(array_keys(instanceValueExceptions()))->toBe(['tests/Feature/LegalPagesTest.php'])
        ->and(instanceNeutralFolders())->toContain('app', 'config', 'database', 'lang', 'routes', 'resources/views', 'apps/knowii/src', 'libs/common/src', 'tests')
        ->and(instanceNeutralFiles())->toBe(['public/humans.txt', '.env.example', '.env.production.example']);
});

test('the guard finds each value, in any case (#1643, #1653)', function (string $text) {
    $found = array_filter(instanceValuePatterns(), static fn (string $pattern): bool => preg_match($pattern, $text) === 1);

    expect($found)->not->toBe([]);
})->with([
    'the product name' => ['Continue with '.'Knowii'.' account'],
    'the product name in lower case' => ['the '.'knowii'.' account of a person'],
    'the product name over a line break' => ['the '.'Knowii'."\n * account"],
    'the account site' => ['https://my.kno'.'wii.net/account'],
    'the sign-in host in upper case' => ['https://AUTH.KNO'.'WII.NET'],
    'the operator' => ['Develo'.'Passion SRL (Belgium)'],
    'the operator in lower case, in an address' => ['contact@develo'.'passion.be'],
    'the operator in two words' => ['the instance of Develo'.' passion'],
    'the founder' => ['S'.'ébastien Dubois'],
    'the founder without the accent, in a handle' => ['https://example.com/dS'.'ebastien'],
    'the founder in upper case' => ['S'.'ÉBASTIEN'],
    'the company number' => ['0717.'.'561.755'],
    'the company number without dots' => ['BE0717'.'561755'],
    'the company number with spaces' => ['0717 '.'561 755'],
    'the street' => ['Route '.'de Mons'],
    'the street over a line break' => ['Route de'."\n    Mons 1"],
    'the town' => ['7130 Bin'.'che'],
    'the host of one instance' => ['https://www.kno'.'wii.net'],
    'the host of one instance in an address' => ['contact@KNO'.'WII.NET'],
]);

test('the guard leaves neutral names alone (#1643, #1653)', function (string $text) {
    $found = array_filter(instanceValuePatterns(), static fn (string $pattern): bool => preg_match($pattern, $text) === 1);

    expect($found)->toBe([]);
})->with([
    'the test label' => ['Continue with Example account'],
    'an enum of the platform' => ['KnowiiAccountDeletionProof::SignIn'],
    'a neutral host' => ['https://accounts.example.com'],
    'the placeholder of the operator' => ['Example Operator'],
    'a neutral operator' => ['Example Ltd (Ireland)'],
    'the placeholder of the contact address' => ['contact@example.com'],
    'the address of the repository' => ['https://github.com/knowii-oss/knowii'],
    'the name of the platform' => ['Knowii runs on knowii-oss/knowii'],
    'a file of the platform' => ['knowii.network.ts'],
    'another company number' => ['0717.561.756'],
]);
