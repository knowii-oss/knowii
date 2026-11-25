#!/usr/bin/env bash
# Wait for the checks of a pull request and merge it (rebase) only when every check is green.
#
# Usage, from the root of the repository:  bash tools/merge/gate-merge.sh <pull request number>
#
# Why: `gh pr merge` does not stop on a failed check when the person may bypass the branch protection. This script reads
# the conclusion of every check first. Run it in the background: the checks take about ten minutes.
# When the working tree changes branch during the wait, run a copy of this file from a temporary folder.
#
# It waits in a loop (`gh pr checks --watch` can return while a long job is still pending): it polls every
# GATE_POLL_SECONDS (60) until no check is pending and every required check is there, for GATE_TIMEOUT_MINUTES (90) at
# most. The required checks are the ones of the branch protection of main; when the protection cannot be read, the list
# below.
set -u
n="${1:?usage: gate-merge.sh <pull request number>}"
poll="${GATE_POLL_SECONDS:-60}"
timeout_minutes="${GATE_TIMEOUT_MINUTES:-90}"

# The required checks when the branch protection cannot be read (keep it the same as the protection of main)
fallback_required=("main" "image" "Analyze (javascript-typescript)" "Analyze (actions)" "CodeQL")

mapfile -t required < <(gh api 'repos/{owner}/{repo}/branches/main/protection/required_status_checks' --jq '.contexts[]' 2>/dev/null)
if [ "${#required[@]}" -eq 0 ]; then
  required=("${fallback_required[@]}")
fi
required_json=$(printf '%s\n' "${required[@]}" | jq -R . | jq -s -c .)
echo "required checks: $(printf '%s, ' "${required[@]}" | sed 's/, $//')"

# One line for each check: name, then DONE or PENDING, then the result (SUCCESS, FAILURE, SKIPPED...)
checks() {
  gh pr view "$n" --json statusCheckRollup --jq '.statusCheckRollup[]
    | if .__typename == "StatusContext"
      then [.context, (if (.state == "PENDING" or .state == "EXPECTED") then "PENDING" else "DONE" end), .state]
      else [.name, (if .status == "COMPLETED" then "DONE" else "PENDING" end), (.conclusion // .status)]
      end
    | @tsv'
}

sleep "$poll"
deadline=$(( $(date +%s) + timeout_minutes * 60 ))
while :; do
  if ! list=$(checks); then
    echo "could not read the checks, trying again"
    sleep "$poll"
    continue
  fi
  pending=$(printf '%s\n' "$list" | awk -F'\t' '$2 == "PENDING"' | wc -l)
  missing=$(printf '%s\n' "$list" | cut -f1 | jq -R . | jq -s -c --argjson required "$required_json" '$required - . | length')
  if [ "$pending" -eq 0 ] && [ "$missing" -eq 0 ]; then
    break
  fi
  if [ "$(date +%s)" -ge "$deadline" ]; then
    echo "time limit of $timeout_minutes minutes: $pending pending, $missing required not started. No merge."
    printf '%s\n' "$list" | awk -F'\t' '{print "  " $1 "=" $3}'
    echo "state: $(gh pr view "$n" --json state --jq .state)"
    exit 1
  fi
  sleep "$poll"
done

count=$(printf '%s\n' "$list" | grep -c .)
bad=$(printf '%s\n' "$list" | awk -F'\t' '$3 != "SUCCESS"' | wc -l)
echo "checks: $count, not green: $bad"
printf '%s\n' "$list" | awk -F'\t' '{print "  " $1 "=" $3}'
if [ "$bad" -eq 0 ]; then
  gh pr merge "$n" --rebase 2>&1 | tail -1
else
  echo "a check is not green: no merge"
fi
echo "state: $(gh pr view "$n" --json state --jq .state)"
