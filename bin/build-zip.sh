#!/usr/bin/env bash
#
# Builds releases/locuentia-<version>.zip, fully clean: git archive of
# HEAD, no development files (export-ignore entries in .gitattributes),
# internal root folder = locuentia/.
#
# The version is read from the locuentia.php header, so every release
# leaves its own ZIP to test in production and run Plugin Check against.
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(sed -n 's/^ \* Version:[[:space:]]*//p' locuentia.php | head -1 | tr -d '[:space:]')
if [ -z "$VERSION" ]; then
	echo "ERROR: could not read the version from locuentia.php" >&2
	exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
	echo "WARNING: there are uncommitted changes; the ZIP is built from the last commit (HEAD)."
fi

mkdir -p releases
OUT="releases/locuentia-${VERSION}.zip"
rm -f "$OUT"

git archive --format=zip --prefix=locuentia/ -o "$OUT" HEAD

echo "Built: $OUT"
unzip -l "$OUT"
echo
echo "Reminder: for the initial WordPress.org submission the file must be named exactly locuentia.zip (copy it renamed)."
