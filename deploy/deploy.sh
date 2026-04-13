#!/usr/bin/env bash
set -euo pipefail

# Shared deploy script for FrankenPHP environments (argos01, argos02).
# Usage: deploy.sh <php_command> <app_dir>
# Example: deploy.sh "frankenphp php-cli" "/home/franken/web/unblock.castris.com"

PHP_CMD="${1:?Usage: deploy.sh <php_command> <app_dir>}"
APP_DIR="${2:?Usage: deploy.sh <php_command> <app_dir>}"

cd "$APP_DIR"

echo ">>> Validate lock file"
composer validate --check-lock --no-interaction || {
    echo "FATAL: composer.lock is out of sync with composer.json"
    echo "The lock file was not updated in the last commit."
    echo "Fix: run 'composer update' locally and commit composer.lock"
    exit 1
}

echo ">>> Clear stale caches before install"
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/packages.php
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/events.php
rm -f bootstrap/cache/routes-v*.php

echo ">>> Composer install"
composer install --no-dev --no-interaction --optimize-autoloader --no-scripts
$PHP_CMD artisan package:discover --ansi

echo ">>> Run migrations"
$PHP_CMD artisan migrate --force

echo ">>> Publish Filament assets"
$PHP_CMD artisan filament:assets

echo ">>> Rebuild caches"
$PHP_CMD artisan config:cache
$PHP_CMD artisan route:cache
$PHP_CMD artisan view:cache
$PHP_CMD artisan event:cache

echo ">>> Deploy complete"
