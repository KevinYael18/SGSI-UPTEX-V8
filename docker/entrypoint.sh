#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-80}"

echo "[entrypoint] Configurando Apache en puerto ${PORT}..."

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "[entrypoint] Esperando a que la base de datos acepte conexiones..."

until php -r "
try {
    new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_CA => getenv('MYSQL_ATTR_SSL_CA'),
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    exit(0);
} catch (Throwable \$e) {
    exit(1);
}
"; do
    echo "[entrypoint] Base de datos no disponible todavía, reintentando en 3s..."
    sleep 3
done

echo "[entrypoint] Base de datos disponible."

cd /var/www/html

if [ ! -f .env ]; then
    echo "[entrypoint] No hay .env, copiando .env.example"
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "[entrypoint] Generando APP_KEY..."
    php artisan key:generate --force
fi

echo "[entrypoint] Ejecutando migraciones..."
php artisan migrate --force

echo "[entrypoint] Cacheando configuración, rutas y vistas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Enlace de storage público..."
php artisan storage:link || true

echo "[entrypoint] Listo. Iniciando supervisord..."
exec "$@"
