#!/bin/bash
# Syntax check for PHP files
# This script checks all PHP files for syntax errors

set -e

echo "Checking PHP syntax..."

find app tests -name "*.php" -print0 | while IFS= read -r -d '' file; do
    php -l "$file" > /dev/null
done

echo "✓ All PHP files have valid syntax"
