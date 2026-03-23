#!/usr/bin/env bash
set -euo pipefail

FILE="${1:-}"
if [[ "$FILE" == "" ]]; then
  echo "Uso: restore-mysql.sh <backup.sql.gz>"
  exit 1
fi

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-emprengrandez}"
DB_USERNAME="${DB_USERNAME:-emprengrandez}"
DB_PASSWORD="${DB_PASSWORD:-}"

gunzip -c "$FILE" | mysql \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  "$DB_DATABASE"

echo "Restore aplicado desde: $FILE"

