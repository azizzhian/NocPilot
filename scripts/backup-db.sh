#!/usr/bin/env bash
# Backup database MySQL sebelum update/migrate.
# Usage:
#   ./scripts/backup-db.sh
#   DB_DATABASE=nocpilot DB_USERNAME=root ./scripts/backup-db.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BACKUP_DIR="${BACKUP_DIR:-$ROOT/backups}"
mkdir -p "$BACKUP_DIR"

# Baca dari apps/backend/.env bila ada
ENV_FILE="$ROOT/apps/backend/.env"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  set -a
  # Ambil hanya baris DB_* yang aman
  while IFS= read -r line || [[ -n "$line" ]]; do
    case "$line" in
      DB_HOST=*|DB_PORT=*|DB_DATABASE=*|DB_USERNAME=*|DB_PASSWORD=*)
        export "${line?}"
        ;;
    esac
  done < "$ENV_FILE"
  set +a
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-nocpilot}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$BACKUP_DIR/${DB_DATABASE}_${STAMP}.sql.gz"

echo "[backup] ${DB_DATABASE}@${DB_HOST}:${DB_PORT} -> $OUT"

export MYSQL_PWD="$DB_PASSWORD"
mysqldump \
  -h "$DB_HOST" \
  -P "$DB_PORT" \
  -u "$DB_USERNAME" \
  --single-transaction \
  --routines \
  --triggers \
  "$DB_DATABASE" | gzip > "$OUT"
unset MYSQL_PWD

# Simpan 14 backup terakhir
ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +15 | xargs -r rm -f

echo "[backup] Selesai: $OUT"
