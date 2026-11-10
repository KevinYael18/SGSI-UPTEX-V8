#!/usr/bin/env bash
# Reconstruye el entorno COMPLETO desde cero: baja contenedores, borra
# volúmenes (¡pierde datos!), reconstruye imágenes y vuelve a inicializar.
set -euo pipefail
cd "$(dirname "$0")/.."

read -rp "Esto BORRARÁ todos los datos (volúmenes incluidos). ¿Continuar? [escriba 'si']: " confirmacion
if [ "$confirmacion" != "si" ]; then
    echo "Cancelado."
    exit 0
fi

echo "==> Deteniendo y eliminando contenedores + volúmenes..."
docker compose down -v

echo "==> Reconstruyendo imágenes sin cache..."
docker compose build --no-cache

echo "==> Levantando entorno limpio..."
docker compose up -d

./scripts/init-db.sh

echo "==> Migrando + poblando datos base..."
docker compose exec -T app php artisan migrate --seed --force
docker compose exec -T app php artisan sgsi:crear-admin

echo "==> Entorno reconstruido desde cero."