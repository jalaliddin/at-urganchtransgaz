#!/usr/bin/env bash
# Backs up the database and uploaded files of the running stack.
#   scripts/backup.sh [target_dir]          (default: ./backups)
# Creates <target_dir>/<UTC timestamp>/{db.sql.gz, storage.tar.gz, SHA256SUMS} and deletes
# backups older than BACKUP_RETENTION_DAYS (default 14). Uses the application DB user.
set -euo pipefail
cd "$(dirname "$0")/.."

root=${1:-backups}
stamp=$(date -u +%Y%m%dT%H%M%SZ)
out="$root/$stamp"
mkdir -p "$out"

echo "→ database"
docker compose exec -T mysql sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --routines --triggers --no-tablespaces -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  | gzip -9 > "$out/db.sql.gz"

echo "→ files (storage/app)"
docker compose exec -T backend tar -C /var/www/html/storage -czf - app > "$out/storage.tar.gz"

(cd "$out" && sha256sum db.sql.gz storage.tar.gz > SHA256SUMS)

# Sanity: a dump without the migrations table is not a usable backup.
gunzip -c "$out/db.sql.gz" | grep -q 'CREATE TABLE `migrations`' || { echo "backup looks incomplete: $out" >&2; exit 1; }

find "$root" -mindepth 1 -maxdepth 1 -type d -mtime "+${BACKUP_RETENTION_DAYS:-14}" -exec rm -rf {} +
echo "Backup written to $out"
