#!/usr/bin/env bash
# Inicializa la base de datos: espera a MySQL y confirma charset/collation.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Esperando healthcheck de MySQL..."
until [ "$(docker inspect -f '{{.State.Health.Status}}' sgsi-uptex-mysql 2>/dev/null)" = "healthy" ]; do
    sleep 2
done

echo "==> Base de datos MySQL lista (charset/collation aplicados por docker/mysql/init/)."