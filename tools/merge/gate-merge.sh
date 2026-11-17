#!/usr/bin/env bash
# Wait for the checks of a pull request and merge it (rebase) only when every check is green.
#
# Usage, from the root of the repository:  bash tools/merge/gate-merge.sh <pull request number>
#
# Why: `gh pr merge` does not stop on a failed check when the person may bypass the branch protection. This script reads
# the conclusion of every check first. Run it in the background: the checks take about ten minutes.
# When the working tree changes branch during the wait, run a copy of this file from a temporary folder.
set -u
n="${1:?usage: gate-merge.sh <pull request number>}"
# The number of required checks of the branch protection of main
required=5
sleep 60
gh pr checks "$n" --watch > /dev/null 2>&1
bad=$(gh pr view "$n" --json statusCheckRollup --jq '[.statusCheckRollup[] | select((.conclusion // .state) != "SUCCESS")] | length')
count=$(gh pr view "$n" --json statusCheckRollup --jq '.statusCheckRollup | length')
echo "checks: $count, not green: $bad"
gh pr view "$n" --json statusCheckRollup --jq '[.statusCheckRollup[] | .name + "=" + (.conclusion // .state)] | join(", ")'
if [ "$bad" = "0" ] && [ "$count" -ge "$required" ]; then
  gh pr merge "$n" --rebase 2>&1 | tail -1
fi
echo "state: $(gh pr view "$n" --json state --jq .state)"
