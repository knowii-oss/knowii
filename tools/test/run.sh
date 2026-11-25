#!/usr/bin/env bash
# The back-end tests (Pest), fast (#1740): in parallel, without the debugger and the coverage driver, with the opcode
# cache of the command line. Run it where PHP runs: in the container or in CI.
#
#   bash tools/test/run.sh                       the whole suite
#   bash tools/test/run.sh tests/Feature/FooTest.php --filter=bar
#   TEST_PROCESSES=4 bash tools/test/run.sh      fewer processes (the default leaves a quarter of the CPUs free)
#   TEST_COVERAGE=1 bash tools/test/run.sh --coverage   with PCOV, for a coverage report
#
# The settings are in tools/test/php/tests.ini. PHP_INI_SCAN_DIR adds them to the ones of the machine, so every worker
# process of the parallel run gets them too (a -d option reaches the first process only).
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root"

cpus="$(nproc 2>/dev/null || getconf _NPROCESSORS_ONLN 2>/dev/null || echo 4)"
default_processes=$(( cpus * 3 / 4 ))
if [ "$default_processes" -lt 2 ]; then
  default_processes=2
fi
processes="${TEST_PROCESSES:-$default_processes}"

# The variable of the environment wins over the setting of a file: the development container sets XDEBUG_MODE
export XDEBUG_MODE=off
export KNOWII_TEST_PCOV="${TEST_COVERAGE:-0}"
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$root/tools/test/php"

# The parallel run gives each process its own database (testing_test_1, testing_test_2...) and keeps them for the
# next run. --recreate-databases makes them again.
if [ "$processes" -le 1 ]; then
  exec php vendor/bin/pest "$@"
fi
exec php vendor/bin/pest --parallel --processes="$processes" "$@"
