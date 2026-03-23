#!/usr/bin/env bash
set -euo pipefail

OUT_DIR="${OUT_DIR:-./backups}"
TS="$(date +%Y%m%d_%H%M%S)"

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-emprengrandez}"
DB_USERNAME="${DB_USERNAME:-emprengrandez}"
DB_PASSWORD="${DB_PASSWORD:-}"

mkdir -p "$OUT_DIR"

FILE="$OUT_DIR/db_${DB_DATABASE}_${TS}.sql.gz"

mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  --single-transaction \
  --routines \
  --triggers \
  --databases "$DB_DATABASE" \
  | gzip > "$FILE"

echo "Backup creado: $FILE"

