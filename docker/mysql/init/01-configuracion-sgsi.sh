#!/usr/bin/env bash
# Se ejecuta automáticamente en el primer arranque del volumen de MySQL
# (docker-entrypoint-initdb.d soporta .sh además de .sql). La base, el
# usuario y sus privilegios base ya los crea la imagen oficial de MySQL a
# partir de MYSQL_DATABASE/MYSQL_USER/MYSQL_PASSWORD (docker-compose.yml).
# Este script refuerza explícitamente charset/collation con los valores
# REALES definidos en el .env (no hardcodeados), usando las variables que
# la propia imagen de MySQL ya expone en este punto del arranque.
set -euo pipefail

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-SQL
    ALTER DATABASE \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
SQL

echo "[mysql-init] Charset utf8mb4/utf8mb4_unicode_ci confirmado para ${MYSQL_DATABASE}."