#!/usr/bin/env bash
#
# Daily database and private-storage backup.
# Supply DB_DATABASE, DB_USERNAME and DB_PASSWORD from your secret manager;
# never put the password in the cron line. Set BACKUP_REMOTE to an rclone
# destination to copy backups to a different provider.
set -euo pipefail

# Dumps hold salaries, phone numbers and password hashes: owner-only files.
umask 077

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

# Read a few settings from the app .env without executing it. The file is
# dotenv, not shell: values may contain spaces, $ or #. Variables already set
# in the environment win.
load_env_value() {
    local key="$1" line value
    [ -n "${!key:-}" ] && return 0
    [ -f "$APP_DIR/.env" ] || return 0
    line="$(grep -E "^${key}=" "$APP_DIR/.env" | tail -n 1 || true)"
    [ -n "$line" ] || return 0
    value="${line#*=}"
    case "$value" in
        \"*\") value="${value#\"}"; value="${value%\"}" ;;
        \'*\') value="${value#\'}"; value="${value%\'}" ;;
    esac
    printf -v "$key" '%s' "$value"
    export "$key"
}

for key in DB_DATABASE DB_USERNAME DB_PASSWORD DB_HOST DB_PORT BACKUP_DIR BACKUP_REMOTE; do
    load_env_value "$key"
done

BACKUP_DIR="${BACKUP_DIR:-/var/backups/khazna}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

: "${DB_DATABASE:?Set DB_DATABASE}"
: "${DB_USERNAME:?Set DB_USERNAME}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
BACKUP_REMOTE="${BACKUP_REMOTE:-}"

mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/storage"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"

# Write to a temporary name and rename only on success, so a failed dump never
# leaves a truncated file that looks like a valid backup.
db_tmp="$BACKUP_DIR/db/.khazna-$stamp.sql.gz.partial"
storage_tmp="$BACKUP_DIR/storage/.khazna-private-$stamp.tar.gz.partial"
trap 'rm -f "$db_tmp" "$storage_tmp"' EXIT

export MYSQL_PWD="${DB_PASSWORD:-}"
mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_DATABASE" | gzip > "$db_tmp"
unset MYSQL_PWD

# mysqldump ends a complete dump with a "Dump completed" comment; refuse to
# publish anything that is not a readable gzip stream with that trailer.
gzip -t "$db_tmp"
if ! gzip -dc "$db_tmp" | tail -n 5 | grep -q 'Dump completed'; then
    printf 'Database dump looks incomplete; not publishing it.\n' >&2
    exit 1
fi
mv "$db_tmp" "$BACKUP_DIR/db/khazna-$stamp.sql.gz"

if [ -d "$APP_DIR/storage/app/private" ]; then
    tar -czf "$storage_tmp" -C "$APP_DIR/storage/app/private" .
    tar -tzf "$storage_tmp" >/dev/null
    mv "$storage_tmp" "$BACKUP_DIR/storage/khazna-private-$stamp.tar.gz"
fi

if [ -n "$BACKUP_REMOTE" ]; then
    if ! command -v rclone >/dev/null 2>&1; then
        printf 'BACKUP_REMOTE is set but rclone is not installed; the off-site copy was NOT made.\n' >&2
        exit 1
    fi

    rclone copy "$BACKUP_DIR" "$BACKUP_REMOTE"
    # rclone copy never deletes, so apply the same retention on the remote.
    rclone delete "$BACKUP_REMOTE" --min-age "${RETENTION_DAYS}d"
fi

find "$BACKUP_DIR" -type f \( -name '*.sql.gz' -o -name '*.tar.gz' -o -name '*.partial' \) -mtime "+$RETENTION_DAYS" -delete

printf 'Backup written to %s at %s\n' "$BACKUP_DIR" "$stamp"
