#!/usr/bin/env bash
# Builds the release ZIP from the committed state (HEAD) with plugin.manifest at the top level.
# Files marked export-ignore in .gitattributes (dev/, tests/, ...) are left out.
set -euo pipefail

cd "$(dirname "$0")/.."

if ! git diff --quiet HEAD -- . ':!dev/legacy-raumplan'; then
    echo "Warnung: Es gibt nicht committete Änderungen, sie sind NICHT im ZIP." >&2
fi

version=$(sed -n 's/^version=//p' plugin.manifest | tr -d '[:space:]')
out="AvailSchedule-${version}.zip"

git archive --format=zip -o "$out" HEAD
echo "$out"
unzip -l "$out"
