#!/usr/bin/env bash
# Format the staged files, check the message, commit (the hooks cannot run on the Windows host)
set -euo pipefail
bash scripts/git-hooks/format-staged.sh
npx --no -- commitlint --edit .git/MSG
git commit -q -F .git/MSG
git log --oneline -1
git status --short
