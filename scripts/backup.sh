#!/usr/bin/env bash
# Respaldo completo: dump de MySQL + archivos persistentes (storage/app).
# Uso: ./scripts/backup.sh [directorio_destino]
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && source .env

DESTINO="${1:-./backups}"
FECHA="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DESTINO"

echo "==> Respaldando base de datos (${DB_DATABASE})..."
docker compose exec -T mysql sh -c "exec mysqldump -u root -p\"\${MYSQL_ROOT_PASSWORD}\" --single-transaction --routines --triggers \"${DB_DATABASE}\"" \
    > "${DESTINO}/db_${FECHA}.sql"

echo "==> Comprimiendo dump..."
gzip -f "${DESTINO}/db_${FECHA}.sql"

echo "==> Respaldando archivos persistentes (evidencias, documentos)..."
docker run --rm \
    -v sgsi-uptex_storage_data:/data:ro \
    -v "$(pwd)/${DESTINO}":/backup \
    alpine \
    tar -czf "/backup/storage_${FECHA}.tar.gz" -C /data .

echo "==> Respaldo completo en ${DESTINO}/db_${FECHA}.sql.gz y ${DESTINO}/storage_${FECHA}.tar.gz"
echo "!! Recomendado: copiar estos archivos fuera del servidor (otro disco, S3, etc.)."