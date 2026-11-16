# syntax=docker/dockerfile:1
# ============================================================================
# SGSI UPTex — Contenedor 1: Apache 2.4 + PHP 8.4 + Laravel 12 + Vue (compilado)
# Arquitectura de 2 contenedores (ver docs/02-Decision-Arquitectura-2-Contenedores.md)
# ============================================================================

# ---- Etapa 1: build del frontend Vue con Node (Node NO se despliega,
#      solo se usa para compilar; el resultado se copia a la imagen final) ----
FROM node:20-bookworm-slim AS frontend-build
WORKDIR /frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# ---- Etapa 2: proyecto Laravel real (scaffold oficial) + nuestro código ----
# Este repositorio solo versiona el CÓDIGO DE DOMINIO (app/, database/,
# routes/api_v1.php, resources/views/reportes) — no el esqueleto base de
# Laravel (artisan, bootstrap/, public/index.php, config/*.php), igual que
# no se versiona vendor/. Esta etapa genera el esqueleto oficial y fusiona
# nuestro código encima, para que la imagen sea 100% autocontenida sin
# pasos manuales (requisito explícito de la Fase 4).
FROM composer:2 AS composer-build
WORKDIR /build

# 1) Archivos del esqueleto oficial de Laravel 12 (artisan, bootstrap/,
#    config/, public/...) SIN instalar dependencias: las versiones las fija
#    el composer.lock versionado, no lo que haya en Packagist el día del build.
RUN composer create-project laravel/laravel:^12.0 . --no-interaction --no-scripts --prefer-dist --no-install

# 2) Dependencias exactas del proyecto (composer.json + composer.lock del repo).
#    --ignore-platform-reqs: la imagen `composer:2` no trae ext-gd ni otras
#    extensiones que SÍ se instalan en la imagen final (etapa `app`,
#    php:8.4-apache); sin el flag, Composer validaría contra el PHP equivocado.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# 3) Scaffolding de Sanctum en modo API (genera routes/api.php + publica config).
#    install:api ejecuta internamente `composer require laravel/sanctum:^4.0`
#    (por eso COMPOSER_IGNORE_PLATFORM_REQS) y puede subir Sanctum o reescribir
#    composer.json; se vuelven a copiar el json y el lock y se reinstala para
#    quedar exactamente en las versiones bloqueadas.
RUN COMPOSER_IGNORE_PLATFORM_REQS=1 php artisan install:api --no-interaction --without-migration-prompt
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs
# install:api genera el archivo de personal_access_tokens con la fecha/hora
# EXACTA del momento del build en el nombre — eso significa que cada
# reconstrucción produce un nombre distinto, y Laravel cree que es una
# migración nueva aunque la tabla física ya exista de un build anterior
# con otro nombre. Se normaliza a un nombre fijo para que sea estable.
RUN for f in database/migrations/*_create_personal_access_tokens_table.php; do \
        mv "$f" database/migrations/2025_01_15_000000_create_personal_access_tokens_table.php; \
    done
# 4) El dominio SGSI no usa la tabla `users` por defecto de Laravel (usa su
#    propia tabla `usuarios`, ver Fase 2 §5.1), y versiona su PROPIA
#    migración de `sessions` (ver database/migrations/..._create_sessions_table.php)
#    para no depender de si el scaffold la incluye o no por defecto — se
#    retiran ambos artefactos del esqueleto para evitar migraciones duplicadas.
RUN rm -f database/migrations/*_create_users_table.php \
    && rm -f database/migrations/*_create_sessions_table.php \
    && rm -f app/Models/User.php \
    && rm -f database/factories/UserFactory.php

# 5) Fusionar nuestro código de dominio ENCIMA del esqueleto (sobreescribe
#    routes/api.php con nuestras rutas /api/v1, agrega app/, migraciones,
#    seeders, factories y vistas de reportes propias).
COPY app/ ./app/
COPY database/migrations/ ./database/migrations/
COPY database/seeders/ ./database/seeders/
COPY database/factories/ ./database/factories/
COPY tests/ ./tests/
COPY routes/api_v1.php ./routes/api.php
COPY routes/web.php ./routes/web.php
COPY resources/views/reportes/ ./resources/views/reportes/
COPY resources/views/components/ ./resources/views/components/
COPY resources/views/vendor/ ./resources/views/vendor/
# Plantillas institucionales de correo (HTML + texto plano) y el logotipo que
# se incrusta en ellas como imagen CID (no depende de que el servidor sea
# accesible desde Internet para que el logo se vea en Gmail/Outlook).
COPY resources/views/emails/ ./resources/views/emails/
COPY resources/images/ ./resources/images/
COPY config/sgsi.php ./config/sgsi.php
COPY pint.json ./pint.json
COPY phpunit.xml ./phpunit.xml

# 6) Registrar nuestros Providers propios (Laravel 11+/12 usa
#    bootstrap/providers.php en vez de config/app.php).
RUN php -r '\
    $path = "bootstrap/providers.php"; \
    $contenido = file_get_contents($path); \
    $extra = "    App\\Providers\\RepositoryServiceProvider::class,\n    App\\Providers\\AuthServiceProvider::class,\n    App\\Providers\\EventServiceProvider::class,\n"; \
    $contenido = str_replace("return [\n", "return [\n" . $extra, $contenido); \
    file_put_contents($path, $contenido);'

# 6b) config/auth.php apunta por defecto a App\Models\User (eliminado en el
#     paso 4) — se repunta el provider 'users' a nuestro App\Models\Usuario.
RUN sed -i "s/use App\\\\Models\\\\User;/use App\\\\Models\\\\Usuario as User;/" config/auth.php
# 6c) Sanctum en modo SPA requiere el middleware statefulApi() explícito
#     dentro de withMiddleware() — install:api no lo agrega en todos los
#     casos, y sin esto las rutas /api/v1 no tienen sesión disponible,
#     fallando con "Session store not set on request." en el login.
RUN sed -i '/function (Middleware \$middleware): void {/a\        $middleware->statefulApi();' bootstrap/app.php
RUN sed -i '/\$middleware->statefulApi();/a\        $middleware->trustProxies(at: '"'"'*'"'"');' bootstrap/app.php

# 6d) Laravel trae 'timezone' => 'UTC' de fábrica en config/app.php —
#     eso hacía que now() del backend corriera 6 horas adelantado
#     respecto a la hora real de México, rechazando como "fecha futura"
#     cualquier hora local que mandara el frontend (ej. al reportar un
#     incidente con la hora actual).
RUN sed -i "s#'timezone' => 'UTC',#'timezone' => 'America/Mexico_City',#" config/app.php

# 6e) Correo y recuperación de contraseña: Laravel 12 ignora MAIL_ENCRYPTION
#     (solo lee MAIL_SCHEME); este script lo vuelve a respetar, exige STARTTLS
#     cuando MAIL_ENCRYPTION=tls, acota el timeout SMTP y hace configurable la
#     vigencia del token. Falla el build si el esqueleto cambió y no aplica.
COPY docker/laravel/configurar-correo.php /tmp/configurar-correo.php
RUN php /tmp/configurar-correo.php /build && rm -f /tmp/configurar-correo.php

# 7) Nota: NO se regeneran migraciones de sessions/cache/jobs con
#    `session:table`/`cache:table`/`queue:table` — Laravel 12 ya las
#    incluye por defecto en el esqueleto (create-project). Volver a
#    generarlas crearía migraciones duplicadas y `artisan migrate` fallaría
#    con "table already exists". Los drivers 'database' de CACHE_STORE,
#    SESSION_DRIVER y QUEUE_CONNECTION (ver docker-compose.yml) ya
#    encuentran esas tablas sin ningún paso adicional.

# 8) Autoload optimizado. Se CONSERVAN las dependencias de desarrollo
#    (Pest, Pint, Mockery) en esta etapa a propósito: el pipeline de CI
#    (.github/workflows/ci.yml) ejecuta los tests DENTRO de esta misma
#    imagen (ver esa nota ahí) para no duplicar la lógica de scaffolding
#    de Laravel en dos lugares distintos. Es un trade-off consciente de
#    tamaño de imagen vs. simplicidad/consistencia — aceptable para el
#    tamaño de este proyecto (no es una imagen que escale a miles de
#    despliegues concurrentes).
RUN composer dump-autoload --optimize --no-interaction

# ---- Etapa 3: imagen final Apache + PHP 8.4 ----
FROM php:8.4-apache AS app

LABEL org.opencontainers.image.title="SGSI UPTex" \
      org.opencontainers.image.description="Backend Laravel 12 + Frontend Vue 3 compilado, Apache+PHP en un solo contenedor" \
      org.opencontainers.image.vendor="Universidad Politecnica de Texcoco"

# --- Extensiones PHP necesarias para Laravel 12 + MySQL 8 + PDF + QR ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        libzip-dev unzip git curl libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libonig-dev libxml2-dev libicu-dev supervisor cron gosu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli zip gd bcmath opcache exif intl \
    && a2enmod rewrite headers ssl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Composer (binario, no la imagen completa) ---
COPY --from=composer-build /usr/bin/composer /usr/bin/composer

# --- Configuración Apache: VirtualHost + mod_rewrite + cabeceras de seguridad ---
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/security.conf /etc/apache2/conf-available/security.conf

RUN a2enconf security
# --- php.ini de producción ---
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-sgsi-uptex.ini

# --- supervisord: Apache + queue:work + schedule:work en el MISMO contenedor
#     (restricción de "solo 2 contenedores" — ver decisión arquitectónica) ---
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# --- Código de la aplicación (esqueleto Laravel + nuestro dominio fusionado,
#     con vendor/ ya resuelto desde la etapa Composer) ---
COPY --from=composer-build /build ./
# El build de Vite (index.html + assets/) se copia a la RAÍZ de public/,
# junto al index.php de Laravel — Apache sirve index.html/assets/* como
# archivos estáticos reales (mod_rewrite los deja pasar, ver
# docker/apache/000-default.conf); cualquier otra ruta cae a routes/web.php
# que devuelve el mismo index.html (fallback de SPA).
COPY --from=frontend-build /frontend/dist ./public

# --- Permisos: www-data debe poder escribir storage/ y bootstrap/cache/ ---
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]