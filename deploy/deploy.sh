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

# public/build is not in git. Where Node exists it is built here; on a host
# without Node (shared hosting) it must be uploaded beforehand, and the deploy
# stops before touching the site if it is missing.
HAS_NPM=1
if ! command -v "$NPM_BIN" >/dev/null 2>&1; then
    HAS_NPM=0

    if [ ! -f public/build/manifest.json ]; then
        printf 'npm is not available and public/build is missing. Upload a built public/build first.\n' >&2
        exit 1
    fi

    printf 'npm not found: skipping the frontend build and using the uploaded public/build.\n'
fi

# Before `git pull` succeeds nothing has changed, so a failure there simply
# brings the site back up. After it, the code, assets and schema may be out of
# step, so the site deliberately stays in maintenance mode: serving traffic
# would be worse than downtime. Fix the cause, then re-run or roll back by
# hand and run `artisan up`.
released=0

on_error() {
    if [ "$released" = "0" ]; then
        printf 'Deploy failed before any change was applied; bringing the app back up.\n' >&2
        "$PHP_BIN" artisan up || true
    else
        printf 'Deploy failed part-way; the app is left in maintenance mode if it was taken down. Fix the problem and re-run, or roll back, then run "%s artisan up".\n' "$PHP_BIN" >&2
    fi
}

trap on_error ERR

"$PHP_BIN" artisan down --retry=60 || true

git pull --ff-only
released=1

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
if [ "$HAS_NPM" = "1" ]; then
    "$NPM_BIN" ci
    "$NPM_BIN" run build
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

if [ "$QUEUE_RESTART" = "1" ]; then
    "$PHP_BIN" artisan queue:restart
fi

"$PHP_BIN" artisan up

trap - ERR
