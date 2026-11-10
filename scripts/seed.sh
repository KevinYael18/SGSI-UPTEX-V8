#!/usr/bin/env bash
# Ejecuta únicamente los seeders (asume que las migraciones ya corrieron).
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec app php artisan db:seed --force