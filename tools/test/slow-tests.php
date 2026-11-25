<?php

/*
 * The slow back-end tests (#1740): reads the JUnit file of a run, writes a GitHub warning for each test slower than the
 * limit, and the slowest tests as a Markdown table: in the summary of the run on GitHub (GITHUB_STEP_SUMMARY), else on
 * the output. It never fails the build.
 *
 *   php tools/test/slow-tests.php junit.xml            the limit is SLOW_TEST_SECONDS, else 10 seconds
 */

$file = $argv[1] ?? 'junit.xml';
$limit = (float) (getenv('SLOW_TEST_SECONDS') ?: 10);

if (! is_file($file)) {
    echo "No JUnit file ($file): the tests did not run.\n";
    exit(0);
}

$xml = simplexml_load_file($file);
if ($xml === false) {
    echo "The JUnit file ($file) cannot be read.\n";
    exit(0);
}

$tests = [];
foreach ($xml->xpath('//testcase') ?: [] as $case) {
    $tests[] = [
        'time' => (float) $case['time'],
        'file' => (string) ($case['file'] ?? ''),
        'name' => (string) $case['name'],
    ];
}
usort($tests, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

$total = array_sum(array_column($tests, 'time'));
$slow = array_filter($tests, static fn (array $test): bool => $test['time'] > $limit);

foreach ($slow as $test) {
    $path = preg_replace('/::.*$/', '', $test['file']);
    $path = preg_replace('#^.*?/(tests/)#', '$1', (string) $path);
    // A workflow command ends at the end of the line: the name of a test can hold none
    $name = str_replace(["\r", "\n", '%'], [' ', ' ', '%25'], $test['name']);
    printf("::warning file=%s,title=Slow test (%.1f s, limit %.0f s)::%s\n", $path, $test['time'], $limit, $name);
}

$table = sprintf("### The slowest back-end tests\n\n%d tests, %.0f seconds of test time, %d slower than %.0f s\n\n", count($tests), $total, count($slow), $limit);
$table .= "| Seconds | Test |\n| ---: | --- |\n";
foreach (array_slice($tests, 0, 15) as $test) {
    $label = basename((string) preg_replace('/::.*$/', '', $test['file'])).' › '.$test['name'];
    $table .= sprintf("| %.2f | %s |\n", $test['time'], str_replace('|', '\\|', mb_strimwidth($label, 0, 160, '…')));
}

$summary = getenv('GITHUB_STEP_SUMMARY');
if (is_string($summary) && $summary !== '') {
    file_put_contents($summary, $table."\n", FILE_APPEND);
} else {
    echo $table;
}
