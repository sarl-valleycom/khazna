#!/usr/bin/env bash
#
# Portable deploy script. Run it from the app directory on staging first.
# Adjust APP_DIR, PHP_BIN, COMPOSER_BIN or NPM_BIN through the environment.
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
QUEUE_RESTART="${QUEUE_RESTART:-1}"

cd "$APP_DIR"

on_error() {
    "$PHP_BIN" artisan up || true
}

trap on_error ERR

"$PHP_BIN" artisan down --retry=60 || true

git pull --ff-only

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$NPM_BIN" ci
"$NPM_BIN" run build

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

if [ "$QUEUE_RESTART" = "1" ]; then
    "$PHP_BIN" artisan queue:restart
fi

"$PHP_BIN" artisan up

trap - ERR
