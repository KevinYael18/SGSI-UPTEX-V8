#!/usr/bin/env bash
# Restaura un respaldo generado por backup.sh.
# Uso: ./scripts/restore.sh <ruta_dump.sql.gz> [ruta_storage.tar.gz]
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && source .env

DUMP="${1:?Debe indicar la ruta del dump .sql.gz. Uso: ./scripts/restore.sh backups/db_XXXX.sql.gz [backups/storage_XXXX.tar.gz]}"
STORAGE_TAR="${2:-}"

echo "!! Esto SOBREESCRIBIRÁ la base de datos actual (${DB_DATABASE})."
read -rp "¿Continuar? [escriba 'si']: " confirmacion
[ "$confirmacion" = "si" ] || { echo "Cancelado."; exit 0; }

echo "==> Restaurando base de datos desde ${DUMP}..."
gunzip -c "$DUMP" | docker compose exec -T mysql sh -c "exec mysql -u root -p\"\${MYSQL_ROOT_PASSWORD}\" \"${DB_DATABASE}\""

if [ -n "$STORAGE_TAR" ]; then
    echo "==> Restaurando archivos persistentes desde ${STORAGE_TAR}..."
    docker run --rm \
        -v sgsi-uptex_storage_data:/data \
        -v "$(pwd)/$(dirname "$STORAGE_TAR")":/backup \
        alpine \
        sh -c "rm -rf /data/* && tar -xzf /backup/$(basename "$STORAGE_TAR") -C /data"
fi

echo "==> Limpiando cachés de la aplicación tras la restauración..."
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear

echo "==> Restauración completa."