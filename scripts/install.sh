#!/usr/bin/env bash
# Instalación completa desde cero — un solo comando, sin pasos manuales.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "==> No existe .env, copiando .env.example"
    cp .env.example .env
    echo "!! Edite .env y defina DB_PASSWORD / DB_ROOT_PASSWORD / ADMIN_PASSWORD antes de continuar."
    exit 1
fi

echo "==> Construyendo y levantando contenedores..."
docker compose up -d --build

echo "==> Esperando a que la app esté saludable..."
until [ "$(docker inspect -f '{{.State.Health.Status}}' sgsi-uptex-app 2>/dev/null)" = "healthy" ]; do
    sleep 3
    echo "    (esperando healthcheck de 'app'...)"
done

echo "==> Ejecutando migraciones + seeders..."
docker compose exec -T app php artisan migrate --seed --force

echo "==> Creando usuario administrador inicial..."
docker compose exec -T app php artisan sgsi:crear-admin

echo "==> Listo. La aplicación está disponible en \${APP_URL:-http://localhost:8080}"