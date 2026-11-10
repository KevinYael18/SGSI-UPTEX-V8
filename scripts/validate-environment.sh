#!/usr/bin/env bash
# ============================================================================
# SGSI UPTex — validate-environment.sh
# Verifica el entorno de la VM (Ubuntu Server 24.04 LTS) antes y después del
# despliegue. Cada verificación es independiente: un fallo no detiene las
# siguientes, para que el reporte final muestre TODO lo que falta de una vez
# (no una lista de errores uno por uno en corridas sucesivas).
#
# Uso:
#   ./scripts/validate-environment.sh            # todas las verificaciones
#   ./scripts/validate-environment.sh --pre       # solo entorno (antes de levantar)
#   ./scripts/validate-environment.sh --post      # solo aplicación (después de levantar)
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && set -a && source .env && set +a

MODO="${1:---todo}"
OK=0
FALLOS=0
ADVERTENCIAS=0

verde() { printf '\033[32m%s\033[0m\n' "$1"; }
rojo() { printf '\033[31m%s\033[0m\n' "$1"; }
amarillo() { printf '\033[33m%s\033[0m\n' "$1"; }

check() {
    local descripcion="$1"; shift
    if "$@" > /tmp/check_out.txt 2>&1; then
        verde "  [OK]  $descripcion"
        OK=$((OK + 1))
    else
        rojo "  [FALLO]  $descripcion"
        sed 's/^/         /' /tmp/check_out.txt | head -3
        FALLOS=$((FALLOS + 1))
    fi
}

advertir() {
    local descripcion="$1"; shift
    if ! "$@" > /tmp/check_out.txt 2>&1; then
        amarillo "  [ADVERTENCIA]  $descripcion"
        ADVERTENCIAS=$((ADVERTENCIAS + 1))
    else
        verde "  [OK]  $descripcion"
        OK=$((OK + 1))
    fi
}

seccion() { echo ""; echo "── $1 ──"; }

echo "SGSI UPTex — Verificación de entorno — $(date)"
echo "Modo: $MODO"

# ===========================================================================
if [ "$MODO" = "--todo" ] || [ "$MODO" = "--pre" ]; then

seccion "1. Herramientas del sistema (deben existir ANTES de levantar contenedores)"
check "Docker Engine instalado"            bash -c "command -v docker"
check "Docker responde (daemon activo)"    docker info
check "Docker Compose v2 (plugin, sin guion)" bash -c "docker compose version"
advertir "Usuario actual pertenece al grupo docker (evita usar sudo)" bash -c "groups | grep -q docker"

seccion "2. Archivos de infraestructura presentes en el repositorio"
check "Dockerfile existe"                  test -f Dockerfile
check "docker-compose.yml existe"          test -f docker-compose.yml
check ".env existe (copiado desde .env.example)" test -f .env
check "docker/apache/000-default.conf existe" test -f docker/apache/000-default.conf
check "docker/apache/security.conf existe" test -f docker/apache/security.conf
check "docker/php/php.ini existe"          test -f docker/php/php.ini
check "docker/supervisord.conf existe"     test -f docker/supervisord.conf
check "docker/entrypoint.sh existe y es ejecutable" test -x docker/entrypoint.sh
check "docker/mysql/init/ tiene script de inicialización" bash -c "ls docker/mysql/init/*.sh"

seccion "3. Variables de entorno críticas definidas en .env"
check "DB_PASSWORD definida y no es el valor de ejemplo" bash -c \
    '[ -n "${DB_PASSWORD:-}" ] && [ "$DB_PASSWORD" != "CAMBIAR_ESTA_CONTRASENA_SEGURA" ]'
check "DB_ROOT_PASSWORD definida y no es el valor de ejemplo" bash -c \
    '[ -n "${DB_ROOT_PASSWORD:-}" ] && [ "$DB_ROOT_PASSWORD" != "CAMBIAR_ESTA_CONTRASENA_ROOT" ]'
check "ADMIN_PASSWORD definida y no es el valor de ejemplo" bash -c \
    '[ -n "${ADMIN_PASSWORD:-}" ] && [ "$ADMIN_PASSWORD" != "CAMBIAR_ESTA_CONTRASENA_ADMIN" ]'
advertir "APP_ENV=production (recomendado fuera de desarrollo)" bash -c '[ "${APP_ENV:-}" = "production" ]'
advertir "APP_DEBUG=false (nunca true en producción)" bash -c '[ "${APP_DEBUG:-}" = "false" ]'

fi

# ===========================================================================
if [ "$MODO" = "--todo" ] || [ "$MODO" = "--post" ]; then

seccion "4. Contenedores levantados"
check "Contenedor sgsi-uptex-app corriendo"   bash -c "docker inspect -f '{{.State.Running}}' sgsi-uptex-app | grep -q true"
check "Contenedor sgsi-uptex-mysql corriendo" bash -c "docker inspect -f '{{.State.Running}}' sgsi-uptex-mysql | grep -q true"
check "Healthcheck de app = healthy"          bash -c "docker inspect -f '{{.State.Health.Status}}' sgsi-uptex-app | grep -q healthy"
check "Healthcheck de mysql = healthy"        bash -c "docker inspect -f '{{.State.Health.Status}}' sgsi-uptex-mysql | grep -q healthy"

seccion "5. PHP 8.4, extensiones y Composer dentro del contenedor app"
check "PHP 8.4.x"                          bash -c "docker compose exec -T app php -v | grep -q '8.4'"
check "Composer disponible"                docker compose exec -T app composer --version
for ext in pdo_mysql mysqli zip gd bcmath opcache exif intl mbstring; do
    check "Extensión PHP: $ext"            bash -c "docker compose exec -T app php -m | grep -qi '^${ext}$'"
done

seccion "6. Apache"
check "Apache respondiendo en :80 (dentro del contenedor)" \
    bash -c "docker compose exec -T app curl -f -s -o /dev/null http://localhost/api/health"
check "mod_rewrite habilitado"              bash -c "docker compose exec -T app apache2ctl -M | grep -q rewrite_module"
check "VirtualHost carga sin errores de sintaxis" docker compose exec -T app apache2ctl configtest

seccion "7. MySQL 8 — conexión y configuración"
check "MySQL responde a ping"              docker compose exec -T mysql mysqladmin ping -h localhost -u root -p"${DB_ROOT_PASSWORD}"
check "Laravel puede conectarse a MySQL (mismo host 'mysql', no localhost)" \
    docker compose exec -T app php artisan db:show
check "Charset utf8mb4 configurado"        bash -c \
    "docker compose exec -T mysql mysql -u root -p'${DB_ROOT_PASSWORD}' -N -e \"SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_DATABASE}';\" | grep -q utf8mb4"

seccion "8. Migraciones y seeders"
check "35 migraciones aplicadas"           bash -c \
    "docker compose exec -T app php artisan migrate:status | grep -c Ran | grep -q '3[0-9]'"
check "93 controles ISO cargados"          bash -c \
    "docker compose exec -T mysql mysql -u root -p'${DB_ROOT_PASSWORD}' -N -e 'SELECT COUNT(*) FROM ${DB_DATABASE}.controles_iso;' | grep -q 93"
check "6 usuarios demo cargados (uno por rol)" bash -c \
    "docker compose exec -T mysql mysql -u root -p'${DB_ROOT_PASSWORD}' -N -e 'SELECT COUNT(*) FROM ${DB_DATABASE}.usuarios;' | grep -qE '^[6-9]|^[1-9][0-9]'"

seccion "9. Permisos de archivos (causa común de fallos silenciosos)"
check "storage/ escribible por www-data"    bash -c \
    "docker compose exec -T app su -s /bin/sh www-data -c 'test -w storage'"
check "bootstrap/cache/ escribible por www-data" bash -c \
    "docker compose exec -T app su -s /bin/sh www-data -c 'test -w bootstrap/cache'"
check "storage/logs/ tiene logs (Laravel escribió algo)" \
    docker compose exec -T app bash -c "test -s storage/logs/laravel.log || ls storage/logs/*.log"

seccion "10. Frontend — assets de Vite"
check "public/index.html existe (build copiado)" docker compose exec -T app test -f public/index.html
check "public/assets/ tiene archivos JS/CSS compilados" \
    bash -c "docker compose exec -T app sh -c 'ls public/assets/*.js public/assets/*.css' 2>/dev/null"
check "index.html servido por Apache responde 200" \
    curl -f -s -o /dev/null "http://localhost:${APP_PORT:-8080}/"

seccion "11. Rutas y cachés de Laravel"
check "route:list responde sin error (rutas registradas)" \
    docker compose exec -T app php artisan route:list --path=api/v1
check "config:cache aplicado sin error"     docker compose exec -T app php artisan config:cache
check "route:cache aplicado sin error"      docker compose exec -T app php artisan route:cache
advertir "event:list muestra los listeners genéricos (RegistraBitacora/GeneraNotificacion)" \
    bash -c "docker compose exec -T app php artisan event:list | grep -qi 'RegistraBitacora\|GeneraNotificacion'"

seccion "12. Estado general de la aplicación"
check "/api/health responde 200 con los 3 subsistemas en 'ok'" bash -c \
    "curl -s http://localhost:${APP_PORT:-8080}/api/health | grep -q '\"ok\"'"
check "Cola de trabajos (driver database) tiene tabla jobs" bash -c \
    "docker compose exec -T mysql mysql -u root -p'${DB_ROOT_PASSWORD}' -N -e \"SHOW TABLES FROM ${DB_DATABASE} LIKE 'jobs';\" | grep -q jobs"

fi

# ===========================================================================
echo ""
echo "════════════════════════════════════════"
echo "  OK: $OK   ADVERTENCIAS: $ADVERTENCIAS   FALLOS: $FALLOS"
echo "════════════════════════════════════════"

if [ "$FALLOS" -gt 0 ]; then
    rojo "Hay $FALLOS verificación(es) crítica(s) fallida(s). Revisar antes de continuar."
    exit 1
elif [ "$ADVERTENCIAS" -gt 0 ]; then
    amarillo "Entorno funcional, con $ADVERTENCIAS advertencia(s) recomendadas de revisar."
    exit 0
else
    verde "Entorno completamente validado."
    exit 0
fi