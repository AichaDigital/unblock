#!/usr/bin/env bash
set -euo pipefail

# Shared deploy script for FrankenPHP environments (argos01, argos02).
# Usage: deploy.sh <php_command> <app_dir>
# Example: deploy.sh "frankenphp php-cli" "/home/franken/web/unblock.castris.com"

PHP_CMD="${1:?Usage: deploy.sh <php_command> <app_dir>}"
APP_DIR="${2:?Usage: deploy.sh <php_command> <app_dir>}"
DEPLOY_ROLE="${DEPLOY_ROLE:?DEPLOY_ROLE must be primary or standby}"

case "$DEPLOY_ROLE" in
    primary|standby) ;;
    *)
        echo "FATAL: DEPLOY_ROLE must be primary or standby"
        exit 64
        ;;
esac

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

if [ "$DEPLOY_ROLE" = "primary" ]; then
    echo ">>> Run migrations"
    $PHP_CMD artisan migrate --force
else
    echo ">>> Skipping migrations on standby (cold node, database restored from Litestream on failover)"
fi

echo ">>> Publish Filament assets"
$PHP_CMD artisan filament:assets

echo ">>> Rebuild caches"
$PHP_CMD artisan config:cache
$PHP_CMD artisan route:cache
$PHP_CMD artisan view:cache
$PHP_CMD artisan event:cache

if [ "$DEPLOY_ROLE" = "primary" ]; then
    echo ">>> Reload FrankenPHP workers"
    FRANKENPHP_PID="$(systemctl show frankenphp --property=MainPID --value 2>/dev/null || true)"
    if [ -n "$FRANKENPHP_PID" ] && [ "$FRANKENPHP_PID" != "0" ]; then
        kill -USR1 "$FRANKENPHP_PID"
    else
        echo "WARN: no FrankenPHP process found"
    fi
fi

echo ">>> Deploy complete"
