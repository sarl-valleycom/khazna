#!/usr/bin/env bash
#
# Daily database and private-storage backup.
# Supply DB_DATABASE, DB_USERNAME and DB_PASSWORD from your secret manager;
# never put the password in the cron line. Set BACKUP_REMOTE to an rclone
# destination to copy backups to a different provider.
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/khazna}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

: "${DB_DATABASE:?Set DB_DATABASE}"
: "${DB_USERNAME:?Set DB_USERNAME}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
BACKUP_REMOTE="${BACKUP_REMOTE:-}"

mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/storage"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"

export MYSQL_PWD="${DB_PASSWORD:-}"
mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_DATABASE" | gzip > "$BACKUP_DIR/db/khazna-$stamp.sql.gz"
unset MYSQL_PWD

if [ -d "$APP_DIR/storage/app/private" ]; then
    tar -czf "$BACKUP_DIR/storage/khazna-private-$stamp.tar.gz" \
        -C "$APP_DIR/storage/app/private" .
fi

if [ -n "$BACKUP_REMOTE" ] && command -v rclone >/dev/null 2>&1; then
    rclone copy "$BACKUP_DIR" "$BACKUP_REMOTE"
fi

find "$BACKUP_DIR" -type f -mtime "+$RETENTION_DAYS" -delete

printf 'Backup written to %s at %s\n' "$BACKUP_DIR" "$stamp"
