#!/bin/bash
set -uo pipefail

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

APP_CONTAINER="lcs_laravel_backend"

fail() {
    echo -e "${RED}✗ $1${NC}" >&2
    exit 1
}

# Ejecuta un comando y aborta el script si falla (a diferencia de "|| true",
# que se usa a propósito en pasos idempotentes/no críticos más abajo).
run_or_fail() {
    local description="$1"; shift
    if ! "$@"; then
        fail "$description"
    fi
}

echo -e "${BLUE}=========================================="
echo -e "  LCS Backend — Inicio de Desarrollo"
echo -e "==========================================${NC}"

# 1. .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}>>> Creando .env desde .env.example...${NC}"
    cp .env.example .env
fi

# 1b. Asegurar variables que docker-compose.yml necesita sí o sí (si faltan,
# el build de la imagen falla en "groupadd" o el usuario/password de MariaDB
# queda vacío). Si el .env es viejo y no las tiene, se agregan con defaults.
ensure_env_var() {
    local key="$1" value="$2"
    if ! grep -q "^${key}=" .env; then
        echo -e "${YELLOW}>>> Agregando ${key} faltante a .env (default: ${value})${NC}"
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}
ensure_env_var WWWUSER "$(id -u 2>/dev/null || echo 1000)"
ensure_env_var WWWGROUP "$(id -g 2>/dev/null || echo 1000)"
# "sail" hardcodeado a propósito: database/docker-init/01_init_databases.sql
# otorga privilegios a ese usuario; si difiere, los GRANT fallan.
ensure_env_var DB_USERNAME "sail"
ensure_env_var DB_PASSWORD "123456"

# Conexiones de infraestructura: sessions/jobs/cache viven en el módulo Sincro
# (db_sincro), no en la conexión por defecto (dbsiaw). Si el .env
# es viejo y no las trae, la app responde 500 en la primera request ("Table
# db_siaw.sessions doesn't exist") y el queue worker cae en loop.
ensure_env_var SESSION_CONNECTION "dbsincro"
ensure_env_var DB_QUEUE_CONNECTION "dbsincro"
ensure_env_var CACHE_STORE "redis"
ensure_env_var DB_CACHE_CONNECTION "dbsincro"

# 1c. Puertos (APP_PORT y FORWARD_DB_PORT, los usa docker-compose.yml).
# Uso:  ./start.sh -p 9000 -d 3024
#       ./start.sh --port 9000 --db-port 3024
# Sin argumentos y con terminal interactiva, pregunta (Enter = valor actual).
APP_PORT_ARG=""
DB_PORT_ARG=""
while [ $# -gt 0 ]; do
    case "$1" in
        -p|--port)     APP_PORT_ARG="${2:-}"; shift 2 || shift ;;
        --port=*)      APP_PORT_ARG="${1#--port=}"; shift ;;
        -d|--db-port)  DB_PORT_ARG="${2:-}"; shift 2 || shift ;;
        --db-port=*)   DB_PORT_ARG="${1#--db-port=}"; shift ;;
        *)             fail "Argumento desconocido: $1 (usa -p/--port y -d/--db-port)" ;;
    esac
done

# choose_port <VAR_ENV> <valor_por_defecto> <descripcion> <valor_pedido>
# Resuelve el puerto (argumento > pregunta interactiva > valor actual del
# .env > default), lo valida, lo guarda en .env y lo deja en $CHOSEN_PORT.
choose_port() {
    local key="$1" default="$2" label="$3" port="$4" current
    current="$(grep "^${key}=" .env | cut -d'=' -f2 | tr -d ' \r')"
    current="${current:-$default}"
    if [ -z "$port" ] && [ -t 0 ]; then
        read -r -p "Puerto de ${label} [${current}]: " port
    fi
    port="${port:-$current}"
    if ! [[ "$port" =~ ^[0-9]+$ ]] || [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
        fail "Puerto inválido para ${label}: '${port}' (debe ser un número entre 1 y 65535)."
    fi
    if grep -q "^${key}=" .env; then
        sed -i "s/^${key}=.*/${key}=${port}/" .env
    else
        printf '%s=%s\n' "$key" "$port" >> .env
    fi
    echo -e "${GREEN}>>> Puerto de ${label}: ${port}${NC}"
    CHOSEN_PORT="$port"
}
choose_port APP_PORT        8844 "la aplicación"    "$APP_PORT_ARG"
choose_port FORWARD_DB_PORT 3322 "la base de datos" "$DB_PORT_ARG"

# 2. Instalar dependencias Composer ANTES de construir la imagen.
#    El Dockerfile de la imagen se toma de vendor/laravel/sail/runtimes/8.4,
#    así que en un clone limpio (sin vendor/) el build de docker compose
#    fallaría de entrada si se intentara antes de este paso.
if [ ! -f "vendor/laravel/sail/runtimes/8.4/Dockerfile" ]; then
    echo -e "${YELLOW}>>> Instalando dependencias Composer (primera vez)...${NC}"
    run_or_fail "No se pudo instalar Composer (vendor/). Revisa conexión a internet / Docker." \
        docker run --rm -u "$(id -u):$(id -g)" \
            -v "/$(pwd):/var/www/html" \
            -w //var/www/html \
            laravelsail/php84-composer:latest \
            composer install --ignore-platform-reqs
fi

# 3. Construir imagen base primero (queue y scheduler la reutilizan)
echo -e "${GREEN}>>> Construyendo imagen base Laravel...${NC}"
run_or_fail "El build de la imagen Docker falló. Revisa el log de arriba (docker compose build)." \
    docker compose build lcs_laravel_backend

# 4. Levantar todos los contenedores
echo -e "${GREEN}>>> Levantando contenedores Docker...${NC}"
run_or_fail "docker compose up -d falló." \
    docker compose up -d

# 4b. Verificar que el contenedor de la app realmente quedó corriendo antes
#     de seguir — si no, todos los "docker compose exec" de abajo fallarían
#     en silencio y el script terminaría reportando éxito falso.
echo -e "${GREEN}>>> Verificando que ${APP_CONTAINER} esté corriendo...${NC}"
sleep 3
RUNNING="$(docker inspect -f '{{.State.Running}}' "$APP_CONTAINER" 2>/dev/null || echo false)"
if [ "$RUNNING" != "true" ]; then
    echo -e "${RED}Estado de los contenedores:${NC}"
    docker compose ps -a
    echo -e "${RED}Últimas líneas de log de ${APP_CONTAINER}:${NC}"
    docker compose logs --tail=40 "$APP_CONTAINER" 2>&1 || true
    fail "${APP_CONTAINER} no quedó corriendo. Revisa el log de arriba."
fi

DB_USERNAME_VAL="$(grep '^DB_USERNAME=' .env | cut -d'=' -f2 | tr -d ' \r')"
DB_PASSWORD_VAL="$(grep '^DB_PASSWORD=' .env | cut -d'=' -f2 | tr -d ' \r')"

mapfile -t DB_DATABASE_LINES < <(grep -E '^DB_[A-Z0-9]+_DATABASE=' .env)

echo -e "${GREEN}>>> Creando bases de datos declaradas en .env...${NC}"
for db_line in "${DB_DATABASE_LINES[@]}"; do
    db_key="${db_line%%=*}"
    db_name="$(echo "${db_line#*=}" | tr -d ' \r')"
    [ -z "$db_name" ] && continue
    echo -e "${BLUE}  → ${db_name}  (${db_key})${NC}"
    run_or_fail "No se pudo crear/otorgar permisos sobre la base ${db_name}." \
        docker compose exec -T lcs_db mysql -uroot -p"${DB_PASSWORD_VAL}" -e \
            "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${DB_USERNAME_VAL}'@'%';"
done
docker compose exec -T lcs_db mysql -uroot -p"${DB_PASSWORD_VAL}" -e "FLUSH PRIVILEGES;"

# 5. Configuración de Git
echo -e "${GREEN}>>> Configurando seguridad de Git...${NC}"
docker compose exec -T "$APP_CONTAINER" git config --global --add safe.directory /var/www/html

# 6. Crear directorios necesarios
echo -e "${GREEN}>>> Verificando estructura de directorios...${NC}"
docker compose exec -T "$APP_CONTAINER" bash -c "
    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/framework/testing
    mkdir -p storage/api-docs
    mkdir -p storage/logs
    mkdir -p storage/app/public
    mkdir -p storage/app/private
    mkdir -p storage/keys
    mkdir -p bootstrap/cache
    mkdir -p resources/views/vendor/l5-swagger
"

# 7. Permisos de storage
echo -e "${GREEN}>>> Ajustando permisos de storage...${NC}"
docker compose exec -T "$APP_CONTAINER" bash -c "
    chown -R www-data:www-data storage bootstrap/cache resources/views/vendor 2>/dev/null || true
    chmod -R 777 storage bootstrap/cache storage/framework/views storage/framework/cache storage/api-docs
    chmod 1777 /tmp 2>/dev/null || true
"

# 8. App Key
# "key:generate --show" genera una key nueva y la IMPRIME sin guardarla —
# su output siempre contiene "base64:", así que un grep sobre ese output
# nunca detecta si ya había una key real en .env. Hay que revisar el .env
# directamente para no pisar una key existente ni dejar de generar una nueva.
echo -e "${GREEN}>>> Verificando App Key...${NC}"
if ! grep -q '^APP_KEY=base64:' .env; then
    run_or_fail "No se pudo generar APP_KEY." \
        docker compose exec -T "$APP_CONTAINER" php artisan key:generate
fi

# 9. Claves RSA por slot de tiempo (AuthService rota cada 2h) — idempotente,
# el comando no hace nada si el slot actual ya tiene claves.
echo -e "${GREEN}>>> Generando claves RSA (auth:rotate-keys)...${NC}"
run_or_fail "No se pudieron generar las claves RSA (auth:rotate-keys)." \
    docker compose exec -T "$APP_CONTAINER" php artisan auth:rotate-keys

# 10. Dependencias (asegura vendor/ actualizado dentro del contenedor también,
#     por si composer.lock cambió desde el paso 2)
echo -e "${GREEN}>>> Actualizando dependencias...${NC}"
run_or_fail "composer install dentro del contenedor falló." \
    docker compose exec -T "$APP_CONTAINER" composer install --ignore-platform-reqs

# 11. Migraciones. Un solo "migrate --force", sin --path ni --database:
# AppServiceProvider registra cada carpeta de database/migrations/ (Siaw,
# Sincro, y las de negocio que se agreguen), cada migración fija su propia
# $connection y el ledger "migrations" vive centralizado en dbsincro
# (CentralMigrationRepository). Una carpeta nueva se recoge sola.
echo -e "${GREEN}>>> Ejecutando migraciones (ledger central en dbsincro)...${NC}"
run_or_fail "Las migraciones fallaron."     docker compose exec -T "$APP_CONTAINER" php artisan migrate --force

# 12. Limpiar caché (DESPUÉS de las migraciones para asegurar que las tablas existan)
echo -e "${GREEN}>>> Limpiando caché...${NC}"
docker compose exec -T "$APP_CONTAINER" php artisan config:clear 2>/dev/null || true
docker compose exec -T "$APP_CONTAINER" php artisan cache:clear  2>/dev/null || true
docker compose exec -T "$APP_CONTAINER" php artisan view:clear   2>/dev/null || true
docker compose exec -T "$APP_CONTAINER" php artisan route:clear  2>/dev/null || true

# 13. Seeders iniciales
echo -e "${GREEN}>>> Ejecutando seeders iniciales...${NC}"
run_or_fail "Los seeders iniciales fallaron." \
    docker compose exec -T "$APP_CONTAINER" php artisan db:seed --force

# 14. Swagger
echo -e "${GREEN}>>> Generando documentación Swagger...${NC}"
docker compose exec -T "$APP_CONTAINER" chmod -R 777 storage/api-docs storage/framework/views 2>/dev/null || true
docker compose exec -T "$APP_CONTAINER" php artisan l5-swagger:generate

if docker compose exec -T "$APP_CONTAINER" test -f storage/api-docs/api-docs.json; then
    echo -e "${GREEN}✓ Swagger generado correctamente${NC}"
    docker compose exec -T "$APP_CONTAINER" chmod 666 storage/api-docs/api-docs.json 2>/dev/null || true
else
    echo -e "${YELLOW}⚠ No se pudo generar api-docs.json${NC}"
fi

# 15. Optimizar caché
echo -e "${GREEN}>>> Optimizando configuración y rutas...${NC}"
docker compose exec -T "$APP_CONTAINER" php artisan config:cache 2>/dev/null || true
docker compose exec -T "$APP_CONTAINER" php artisan route:cache  2>/dev/null || true

# 16. Mostrar puertos desde .env
APP_PORT=$(grep "^APP_PORT=" .env | cut -d'=' -f2 | tr -d ' '); APP_PORT=${APP_PORT:-8844}
DB_PORT=$(grep "^FORWARD_DB_PORT=" .env | cut -d'=' -f2 | tr -d ' '); DB_PORT=${DB_PORT:-3322}
MAIL_PORT=$(grep "^FORWARD_MAILPIT_DASHBOARD_PORT=" .env | cut -d'=' -f2 | tr -d ' '); MAIL_PORT=${MAIL_PORT:-8026}

echo -e "${BLUE}=========================================="
echo -e "  ✓ LCS BACKEND INICIADO CORRECTAMENTE"
echo -e "${NC}"
echo -e "  Aplicación:        ${GREEN}http://localhost:${APP_PORT}${NC}"
echo -e "  Swagger API:       ${GREEN}http://localhost:${APP_PORT}/api/documentation${NC}"
echo -e "  Base de datos:     ${GREEN}localhost:${DB_PORT}${NC}  (lcs_db)"
# Listado dinámico: mismas entradas DB_<MODULO>_DATABASE del .env que se
# crearon al inicio (array DB_DATABASE_LINES). Un módulo nuevo aparece
# aquí solo, sin tocar este bloque.
_db_total=${#DB_DATABASE_LINES[@]}
_db_i=0
for db_line in "${DB_DATABASE_LINES[@]}"; do
    _db_i=$((_db_i + 1))
    db_key="${db_line%%=*}"
    db_name="$(echo "${db_line#*=}" | tr -d ' \r')"
    [ -z "$db_name" ] && continue
    db_mod="${db_key#DB_}"; db_mod="${db_mod%_DATABASE}"
    if [ "$_db_i" -eq "$_db_total" ]; then branch="└─"; else branch="├─"; fi
    printf "    %s %-16s → %s\n" "$branch" "$db_name" "$db_mod"
done
echo -e "  Mailpit:           ${GREEN}http://localhost:${MAIL_PORT}${NC}"
echo -e ""
echo -e "  Contenedores activos:"
echo -e "  ${YELLOW}lcs_laravel_backend${NC}  → HTTP API"
echo -e "  ${YELLOW}lcs_queue${NC}            → queue:work (jobs)"
echo -e "  ${YELLOW}lcs_scheduler${NC}        → schedule:work (tareas programadas)"
echo -e ""
echo -e "  Comandos artisan (desde host) — ejemplo al agregar el primer módulo de negocio:"
echo -e "  ${YELLOW}docker compose exec lcs_laravel_backend php artisan make:migration create_lcs_xxx_table${NC}"
echo -e "  ${YELLOW}docker compose exec lcs_laravel_backend php artisan make:model Models/dblcs/LcsXxx${NC}"
echo -e "  ${YELLOW}docker compose exec lcs_laravel_backend php artisan make:controller Api/LcsXxxController${NC}"
echo -e "  ${YELLOW}docker compose exec lcs_laravel_backend php artisan migrate --path=database/migrations/{Modulo} --database=dblcs --force${NC}  (negocio propio — se auto-detecta al tener carpeta)"
echo -e "${BLUE}==========================================${NC}"
