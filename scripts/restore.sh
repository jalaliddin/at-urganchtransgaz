#!/usr/bin/env bash
# Restores a backup made by scripts/backup.sh into the running stack.
#   scripts/restore.sh <backup_dir> [--yes]
# OVERWRITES the current database and storage/app. Verifies checksums first.
set -euo pipefail
cd "$(dirname "$0")/.."

dir=${1:?usage: scripts/restore.sh <backup_dir> [--yes]}
[ -f "$dir/db.sql.gz" ] && [ -f "$dir/storage.tar.gz" ] || { echo "not a backup directory: $dir" >&2; exit 1; }
(cd "$dir" && sha256sum --quiet -c SHA256SUMS) || { echo "checksum mismatch in $dir" >&2; exit 1; }

if [ "${2:-}" != "--yes" ]; then
  read -r -p "Restore $dir over the CURRENT database and files? Type 'restore' to continue: " answer
  [ "$answer" = "restore" ] || { echo "cancelled"; exit 1; }
fi

echo "→ database"
gunzip -c "$dir/db.sql.gz" | docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"'

echo "→ files (storage/app)"
docker compose exec -T backend sh -c 'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf {} + && tar -C /var/www/html/storage -xzf -' < "$dir/storage.tar.gz"

echo "→ caches"
docker compose exec -T backend php artisan cache:clear >/dev/null
echo "Restored from $dir"
