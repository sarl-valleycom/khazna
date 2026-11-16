#!/usr/bin/env bash
#
# Portable deploy script. Run it from the app directory on staging first.
# Adjust APP_DIR, PHP_BIN or COMPOSER_BIN through the environment.
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
QUEUE_RESTART="${QUEUE_RESTART:-1}"

cd "$APP_DIR"

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
# The frontend build (public/build) is committed, so there is no Node step here.
# Run `npm run build` locally and commit public/build before pushing frontend changes.

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

if [ "$QUEUE_RESTART" = "1" ]; then
    "$PHP_BIN" artisan queue:restart
fi

"$PHP_BIN" artisan up

trap - ERR
