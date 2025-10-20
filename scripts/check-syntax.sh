#!/usr/bin/env bash
set -euo pipefail

PHP=$(command -v php || true)
if [ -z "$PHP" ]; then
  echo "php not found" >&2
  exit 1
fi

EXIT=0
# Use find with -exec for better compatibility and proper exit code handling
find . -name "*.php" -type f -not -path "./vendor/*" -not -path "./storage/*" -not -path "./bootstrap/cache/*" -exec php -l {} \; || EXIT=$?

exit $EXIT
