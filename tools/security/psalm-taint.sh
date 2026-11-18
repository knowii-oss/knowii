#!/usr/bin/env bash
# The security scan of the PHP code (ADR 0051): the taint analysis of Psalm, with the Laravel plugin.
#
# Usage, from the root of the repository, after `composer install`:  bash tools/security/psalm-taint.sh [<report.sarif>]
#
# Psalm also reports type problems: PHPStan is the type checker of the project, so this script keeps only the results
# whose rule starts with "Tainted". It writes them to a SARIF report (default: psalm-taint.sarif) and fails when there
# is at least one. A result that is safe by design gets an annotation in the code with its reason
# (`@psalm-taint-escape <kind>`), never an entry in a baseline.
#
# A result is a possible security problem: do not paste it in a public issue or pull request (SECURITY.md).
set -euo pipefail

report="${1:-psalm-taint.sarif}"
raw="$(mktemp --suffix=.sarif)"
trap 'rm -f "$raw"' EXIT

# Psalm exits with 2 when it finds a type problem: that is not the job of this scan
vendor/bin/psalm --no-cache --no-progress --threads="$(nproc)" --report="$raw" > /dev/null || true

if [ ! -s "$raw" ]; then
  echo "psalm-taint: Psalm wrote no report" >&2
  exit 2
fi

php -r '
$sarif = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$run = &$sarif["runs"][0];
$names = [];
foreach ($run["tool"]["driver"]["rules"] ?? [] as $rule) {
    $names[$rule["id"]] = $rule["name"] ?? "";
}
$isTaint = static fn (string $id): bool => str_starts_with($names[$id] ?? "", "Tainted");
$run["results"] = array_values(array_filter($run["results"] ?? [], static fn (array $result): bool => $isTaint($result["ruleId"])));
$run["tool"]["driver"]["rules"] = array_values(array_filter($run["tool"]["driver"]["rules"] ?? [], static fn (array $rule): bool => $isTaint($rule["id"])));
file_put_contents($argv[2], json_encode($sarif, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$count = count($run["results"]);
fwrite(STDERR, "psalm-taint: {$count} result(s), report in {$argv[2]}\n");
exit($count === 0 ? 0 : 1);
' "$raw" "$report"
