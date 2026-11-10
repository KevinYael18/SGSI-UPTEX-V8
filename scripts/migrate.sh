#!/usr/bin/env bash
# Ejecuta únicamente las migraciones (sin seeders) — útil en actualizaciones.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec app php artisan migrate --force