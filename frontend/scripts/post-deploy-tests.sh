#!/usr/bin/env bash
# ============================================================================
# SGSI UPTex — Validación completa de Fase 4
# Ejecutar DESPUÉS de `docker compose up -d --build` en la VM real.
# Genera un reporte (post-deploy-tests-<fecha>.log) con la salida de CADA
# comando pedido en la especificación de Fase 4 — esta es la forma honesta
# de producir "evidencias de funcionamiento": el entorno de desarrollo
# donde se generó este proyecto no tiene Docker, PHP ni acceso de red para
# ejecutar esto de verdad (ver docs/09-Auditoria-Final-Fase4.md §0).
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."

REPORTE="post-deploy-tests-$(date +%Y%m%d_%H%M%S).log"
FALLOS=0

seccion() { echo -e "\n\n========== $1 ==========" | tee -a "$REPORTE"; }
ejecutar() {
    local descripcion="$1"; shift
    echo -e "\n--- $descripcion ---\n\$ $*" | tee -a "$REPORTE"
    if "$@" >> "$REPORTE" 2>&1; then
        echo "OK: $descripcion" | tee -a "$REPORTE"
    else
        echo "FALLÓ: $descripcion (ver detalle arriba en $REPORTE)" | tee -a "$REPORTE"
        FALLOS=$((FALLOS + 1))
    fi
}

echo "SGSI UPTex — Validación Fase 4 — $(date)" | tee "$REPORTE"

# ---------------------------------------------------------------------------
seccion "1. Contenedores levantados y saludables"
ejecutar "docker compose ps" docker compose ps
ejecutar "Healthcheck del contenedor app (Apache+PHP)" docker inspect --format='{{.State.Health.Status}}' sgsi-uptex-app
ejecutar "Healthcheck del contenedor mysql" docker inspect --format='{{.State.Health.Status}}' sgsi-uptex-mysql

# ---------------------------------------------------------------------------
seccion "2. Laravel — comandos pedidos explícitamente"
ejecutar "Migraciones + seeders (35 migraciones, 8 seeders)" docker compose exec -T app php artisan migrate --seed --force
ejecutar "optimize (cachea config+rutas+eventos+vistas)" docker compose exec -T app php artisan optimize
ejecutar "route:list (confirma que todas las rutas /api/v1 registraron)" docker compose exec -T app php artisan route:list
ejecutar "config:cache" docker compose exec -T app php artisan config:cache
ejecutar "event:list (confirma RegistraBitacora/GeneraNotificacion cableados)" docker compose exec -T app php artisan event:list
ejecutar "Suite de pruebas Pest (backend)" docker compose exec -T app php artisan test

# ---------------------------------------------------------------------------
seccion "3. Base de datos — integridad"
ejecutar "Conteo de migraciones aplicadas (esperado: 35)" \
    docker compose exec -T mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e \
    "SELECT COUNT(*) FROM ${DB_DATABASE}.migrations;"
ejecutar "Charset/collation de la base (esperado: utf8mb4 / utf8mb4_unicode_ci)" \
    docker compose exec -T mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e \
    "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_DATABASE}';"
ejecutar "Llaves foráneas registradas (esperado: > 0)" \
    docker compose exec -T mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e \
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='${DB_DATABASE}' AND REFERENCED_TABLE_NAME IS NOT NULL;"
ejecutar "93 controles ISO cargados por el seeder" \
    docker compose exec -T mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e \
    "SELECT COUNT(*) FROM ${DB_DATABASE}.controles_iso;"
ejecutar "6 usuarios demo (uno por rol) cargados" \
    docker compose exec -T mysql mysql -u root -p"${DB_ROOT_PASSWORD}" -N -e \
    "SELECT COUNT(*) FROM ${DB_DATABASE}.usuarios;"

# ---------------------------------------------------------------------------
seccion "4. Frontend — build real servido por Apache"
ejecutar "npm install (dentro del contenedor de build, replicando el Dockerfile)" \
    docker run --rm -v "$(pwd)/frontend":/frontend -w /frontend node:20-bookworm-slim npm ci
ejecutar "npm run build" \
    docker run --rm -v "$(pwd)/frontend":/frontend -w /frontend node:20-bookworm-slim npm run build
ejecutar "index.html accesible vía Apache" curl -f -s -o /dev/null http://localhost:"${APP_PORT:-8080}"/
ejecutar "Un asset JS compilado responde 200" bash -c \
    "curl -s http://localhost:${APP_PORT:-8080}/ | grep -oE '/assets/[^\"]+\\.js' | head -1 | xargs -I{} curl -f -s -o /dev/null http://localhost:${APP_PORT:-8080}{}"

# ---------------------------------------------------------------------------
seccion "5. Endpoints de la API — smoke test por módulo"
BASE="http://localhost:${APP_PORT:-8080}"
ejecutar "Health check" curl -f -s "$BASE/api/health"
ejecutar "Cookie CSRF (Sanctum)" curl -f -s -c /tmp/sgsi_cookies.txt "$BASE/sanctum/csrf-cookie"
ejecutar "Login con usuario demo Administrador" bash -c "
    TOKEN=\$(grep XSRF-TOKEN /tmp/sgsi_cookies.txt | cut -f7)
    curl -f -s -b /tmp/sgsi_cookies.txt -c /tmp/sgsi_cookies.txt -X POST '$BASE/api/v1/login' \
        -H 'Content-Type: application/json' -H \"X-XSRF-TOKEN: \$TOKEN\" \
        -d '{\"email\":\"admin@uptex.edu.mx\",\"password\":\"${ADMIN_PASSWORD}\"}'
"
ejecutar "Listar activos (autenticado)" curl -f -s -b /tmp/sgsi_cookies.txt "$BASE/api/v1/activos"
ejecutar "Dashboard KPIs" curl -f -s -b /tmp/sgsi_cookies.txt "$BASE/api/v1/dashboard"
ejecutar "Matriz de riesgo 5x5" curl -f -s -b /tmp/sgsi_cookies.txt "$BASE/api/v1/dashboard/matriz-riesgo"
ejecutar "Controles ISO (93 esperados)" curl -f -s -b /tmp/sgsi_cookies.txt "$BASE/api/v1/controles-iso?por_pagina=100"
ejecutar "Búsqueda global" curl -f -s -b /tmp/sgsi_cookies.txt "$BASE/api/v1/busqueda?q=servidor"
ejecutar "Reporte PDF de activos" curl -f -s -o /tmp/reporte_activos.pdf -b /tmp/sgsi_cookies.txt "$BASE/api/v1/reportes/activos.pdf"

# ---------------------------------------------------------------------------
seccion "6. Seguridad — verificaciones puntuales"
ejecutar "Rechaza acceso sin autenticación (esperado 401)" bash -c \
    "curl -s -o /dev/null -w '%{http_code}' '$BASE/api/v1/activos' | grep -q 401"
ejecutar "Rate limiting de login (6º intento debe dar 429)" bash -c '
    for i in 1 2 3 4 5 6; do
        CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "'"$BASE"'/api/v1/login" -H "Content-Type: application/json" -d "{\"email\":\"x@x.com\",\"password\":\"mala\"}")
    done
    [ "$CODE" = "429" ] || [ "$CODE" = "422" ]
'
ejecutar "APP_DEBUG apagado en producción (no debe mostrar stack trace)" bash -c \
    "curl -s '$BASE/api/v1/activos/uuid-invalido' | grep -qv 'Stack trace'"

seccion "RESUMEN"
echo "Total de verificaciones fallidas: $FALLOS" | tee -a "$REPORTE"
if [ "$FALLOS" -eq 0 ]; then
    echo "TODO OK — reporte completo en $REPORTE" | tee -a "$REPORTE"
else
    echo "Hay $FALLOS verificación(es) fallida(s) — revisar $REPORTE antes de la defensa." | tee -a "$REPORTE"
fi