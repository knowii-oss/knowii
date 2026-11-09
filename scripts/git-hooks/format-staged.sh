#!/usr/bin/env bash
# Pre-commit hook: format the staged files (Prettier, and Pint for PHP) and stage them again.
# Invoked by Git 2.54+ config-based hooks via `.gitconfig` at the repo root.
#
# Only staged files are touched, so a commit never leaves new changes in the working tree.
# A file that also has unstaged changes is skipped: staging it again would also stage those
# changes. Lint, static analysis and audits run in CI (and before a pull request, see AGENTS.md).

set -euo pipefail

# Staged files (added, copied, modified, renamed), NUL-separated to survive whitespace in paths.
mapfile -d '' -t staged_files < <(git diff --cached --name-only --diff-filter=ACMR -z)
if ((${#staged_files[@]} == 0)); then
    exit 0
fi

# Files with unstaged changes in the working tree
mapfile -d '' -t unstaged_files < <(git diff --name-only -z)
declare -A partly_staged=()
for file in "${unstaged_files[@]}"; do
    partly_staged["$file"]=1
done

files=()
php_files=()
for file in "${staged_files[@]}"; do
    if [[ -n "${partly_staged[$file]:-}" ]]; then
        echo "format-staged: skipped ${file}: it also has unstaged changes (format it with 'npm run format')" >&2
        continue
    fi
    files+=("$file")
    if [[ "$file" == *.php ]]; then
        php_files+=("$file")
    fi
done

if ((${#files[@]} == 0)); then
    exit 0
fi

npx --no -- prettier --write --ignore-unknown --log-level warn -- "${files[@]}"

if ((${#php_files[@]} > 0)); then
    if command -v php >/dev/null 2>&1 && [[ -x vendor/bin/pint ]]; then
        vendor/bin/pint --quiet -- "${php_files[@]}"
    else
        echo "format-staged: PHP or vendor/bin/pint is missing: the PHP files are not formatted (CI checks them)" >&2
    fi
fi

git add -- "${files[@]}"
