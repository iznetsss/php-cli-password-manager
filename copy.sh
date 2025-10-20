#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C

# Work in script dir
rootDir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$rootDir"

scriptName="$(basename "${BASH_SOURCE[0]}")"
tmpFile="$(mktemp)"
trap 'rm -f "$tmpFile"' EXIT

# Build output, prune unwanted dirs and files
{
  find . \
    -path './php-cli-password-manager/vendor' -prune -o \
    -path './.git' -prune -o \
    -path './.idea' -prune -o \
    -type f \
    ! -path './php-cli-password-manager/composer.lock' \
    ! -path "./$scriptName" \
    -print0 \
  | sort -z \
  | while IFS= read -r -d '' f; do
      rel="${f#./}"
      printf '%s:\n' "$rel"
      cat -- "$f" || true
      printf '\n\n'
    done
} > "$tmpFile"

# Copy to clipboard (Ubuntu/macOS)
if command -v wl-copy >/dev/null 2>&1; then
  wl-copy < "$tmpFile"
elif command -v xclip >/dev/null 2>&1; then
  xclip -selection clipboard < "$tmpFile"
elif command -v xsel >/dev/null 2>&1; then
  xsel --clipboard --input < "$tmpFile"
elif command -v pbcopy >/dev/null 2>&1; then
  pbcopy < "$tmpFile"
else
  cat "$tmpFile"
  echo "Warning: no clipboard tool found; printed to stdout." >&2
fi
