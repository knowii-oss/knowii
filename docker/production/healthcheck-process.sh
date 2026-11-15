#!/bin/sh
# Health check of the services that have no port: succeeds when a process of this container has the given text in its
# command line (for example "queue:work"). The image has no ps or pgrep, so this reads /proc.
# Usage: knowii-healthcheck-process <text>
set -eu

needle="${1:?Usage: knowii-healthcheck-process <text>}"

for cmdline in /proc/[0-9]*/cmdline; do
    # The arguments are separated by NUL characters. The process can end while we read it.
    line="$(tr '\0' ' ' < "$cmdline" 2>/dev/null || true)"
    case "$line" in
        # This check and the shell that runs it have the text in their own command line
        *knowii-healthcheck-process*) continue ;;
        *"$needle"*) exit 0 ;;
    esac
done

echo "No process matches: $needle" >&2
exit 1
