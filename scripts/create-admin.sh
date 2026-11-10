#!/usr/bin/env bash
# Crea (o promueve a) el usuario Administrador inicial, leyendo
# ADMIN_NOMBRE/ADMIN_EMAIL/ADMIN_PASSWORD del .env si están definidos.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec app php artisan sgsi:crear-admin