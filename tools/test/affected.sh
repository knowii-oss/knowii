#!/usr/bin/env bash
# Only the back-end tests that a change touches (#1740). Run it where PHP runs (in the container), from any folder.
#
#   bash tools/test/affected.sh            the changes against origin/main (the commits of the branch and the files
#                                          that are not committed yet)
#   bash tools/test/affected.sh <base>     against another branch or commit
#
# What it runs, in this order:
#   1. The full suite (tools/test/run.sh), when a change can reach every test: Composer, the PHPUnit configuration,
#      config/, routes/, database/migrations/, database/schema/, app/Providers/, bootstrap/, tests/Pest.php,
#      tests/TestCase.php, tests/Fixtures/, .env files. The script stops there.
#   2. Always: all of tests/Unit, and the feature tests that read the files of the tree (the mail classes, the
#      architecture, the legal texts, the OpenAPI document, the Bruno collection...): a coverage map cannot see a file
#      that a test reads.
#   3. The other tests that the change touches, with the test impact analysis of Pest (`--tia --filtered`). Pest keeps
#      a map of the files that each test runs (recorded with PCOV) and runs again only the tests of the changed files;
#      the first run on a machine records the map, so it runs every test once. The map is kept in ~/.pest/tia (one for
#      each repository, shared by its worktrees). `bash tools/test/affected.sh --fresh` records it again.
#
# A pull request still runs the full suite in CI: this command is for the work before it.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root"

fresh=()
base="origin/main"
for argument in "$@"; do
  case "$argument" in
    --fresh) fresh=(--fresh) ;;
    *) base="$argument" ;;
  esac
done

# A git worktree inside the development container: its .git file points to the path of the host, which the container
# does not have. Point git to the same folder under the mount of the main clone.
if ! git rev-parse --git-dir > /dev/null 2>&1 && [ -f .git ]; then
  gitdir="$(sed -n 's/^gitdir: //p' .git)"
  worktree_name="$(basename "$gitdir")"
  folder="$root"
  while [ "$folder" != "/" ]; do
    folder="$(dirname "$folder")"
    if [ -d "$folder/.git/worktrees/$worktree_name" ]; then
      export GIT_DIR="$folder/.git/worktrees/$worktree_name"
      export GIT_WORK_TREE="$root"
      break
    fi
  done
fi

if ! merge_base="$(git merge-base "$base" HEAD 2>/dev/null)"; then
  echo "affected.sh: no common commit with $base (git fetch origin first?)" >&2
  exit 2
fi

mapfile -t changed < <({
  git diff --name-only "$merge_base"
  git ls-files --others --exclude-standard
} | sort -u)

echo "affected.sh: ${#changed[@]} changed file(s) against $base ($(git rev-parse --short "$merge_base"))"

# 1. A change that can reach every test
full_suite='^(composer\.(json|lock)|phpunit\.xml(\.dist)?|\.env(\..*)?|config/.*|routes/.*|database/migrations/.*|database/schema/.*|app/Providers/.*|bootstrap/.*|tests/Pest\.php|tests/TestCase\.php|tests/Fixtures/.*|tools/test/.*)$'
for file in "${changed[@]}"; do
  if [[ "$file" =~ $full_suite ]]; then
    echo "affected.sh: $file changed: the full suite runs"
    exec bash tools/test/run.sh
  fi
done

# 2. The tests that always run: tests/Unit and the feature tests that read files of the tree
guard_pattern='applicationPhpFiles\(|applicationMailClasses\(|applicationMailSendsWithoutAClass\(|RecursiveDirectoryIterator|File::allFiles\(|Finder|file_get_contents\((base_path|app_path|resource_path|database_path)|scramble:export|[Bb]runo'
mapfile -t guards < <(grep -l -E "$guard_pattern" tests/Feature/*.php | sort)

mkdir -p tmp
always_config="tmp/phpunit-affected-always.xml"
{
  sed -n '1,/<testsuites>/p' phpunit.xml | sed 's#bootstrap="vendor/#bootstrap="../vendor/#; s#"vendor/phpunit/#"../vendor/phpunit/#'
  echo '        <testsuite name="Always">'
  echo '            <directory>../tests/Unit</directory>'
  for file in "${guards[@]}"; do
    echo "            <file>../$file</file>"
  done
  echo '        </testsuite>'
  sed -n '/<\/testsuites>/,$p' phpunit.xml | sed 's#<directory>app</directory>#<directory>../app</directory>#'
} > "$always_config"

echo "affected.sh: tests/Unit and ${#guards[@]} feature file(s) that read the tree"
bash tools/test/run.sh --configuration "$always_config"

# 3. The tests that the change touches (Pest needs a coverage driver to keep its map)
if [ "${#changed[@]}" -eq 0 ] && [ "${#fresh[@]}" -eq 0 ]; then
  echo "affected.sh: no change against $base"
  exit 0
fi
echo "affected.sh: the tests that the change touches"
TEST_COVERAGE=1 bash tools/test/run.sh --tia --filtered "${fresh[@]}"
