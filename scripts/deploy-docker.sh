#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

docker compose -f "$COMPOSE_FILE" pull || true
docker compose -f "$COMPOSE_FILE" up -d --build

docker compose -f "$COMPOSE_FILE" exec -T php php artisan migrate --force
docker compose -f "$COMPOSE_FILE" exec -T php php artisan optimize:clear

