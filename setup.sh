#!/usr/bin/env bash
# setup.sh — Configura el .env inicial y luego se elimina a sí mismo.
# Uso: bash setup.sh

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

SCRIPT_PATH="$(realpath "$0")"

banner() {
    echo -e "${BLUE}"
    echo "  ╔═══════════════════════════════════════════╗"
    echo "  ║   LCS Backend — Configuración               ║"
    echo "  ╚═══════════════════════════════════════════╝"
    echo -e "${NC}"
}

ask() {
    local var="$1" prompt="$2" default="$3"
    if [ -n "$default" ]; then
        echo -ne "  ${prompt} ${YELLOW}[${default}]${NC}: " >&2
    else
        echo -ne "  ${prompt}: " >&2
    fi
    read -r value
    echo "${value:-$default}"
}

ask_secret() {
    local prompt="$1"
    echo -ne "  ${prompt}: " >&2
    read -rs value
    echo "" >&2
    echo "$value"
}

banner

if [ -f .env ]; then
    echo -e "${YELLOW}  ⚠ Ya existe un .env. ¿Sobreescribir? (s/N):${NC} "
    read -r confirm
    [[ "$confirm" =~ ^[sS]$ ]] || { echo "  Cancelado."; rm -f "$SCRIPT_PATH"; exit 0; }
fi

echo -e "${GREEN}  Generando APP_KEY...${NC}"
APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));" 2>/dev/null \
          || openssl rand -base64 32 | tr -d '\n' | sed 's/^/base64:/')

echo ""
echo -e "${BLUE}  ── Entorno ──────────────────────────────────────${NC}"
APP_ENV=$(ask "APP_ENV" "Entorno (local/production)" "local")
APP_DEBUG=$([ "$APP_ENV" = "production" ] && echo "false" || echo "true")
APP_URL=$(ask "APP_URL" "URL de la app" "http://localhost")
APP_PORT=$(ask "APP_PORT" "Puerto HTTP" "8877")

echo ""
echo -e "${BLUE}  ── Base de datos ────────────────────────────────${NC}"
if [ "$APP_ENV" = "production" ]; then
    DB_LCS_HOST=$(ask "DB_LCS_HOST" "Host MariaDB" "mariadb-service")
else
    DB_LCS_HOST="lcs_db"
fi
DB_PASSWORD=$(ask_secret "Password de BD (sail/root)")
DB_ROOT_PASSWORD=$(ask_secret "Password root de BD")

echo ""
echo -e "${BLUE}  ── CORS ─────────────────────────────────────────${NC}"
CORS_ORIGINS=$(ask "CORS" "Orígenes permitidos (separados por coma)" "http://localhost:4200")

echo ""
echo -e "${BLUE}  ── Discord (opcional, Enter para omitir) ────────${NC}"
DISCORD_WEBHOOK=$(ask "DISCORD" "Webhook URL de Discord" "")

# ─── Escribir .env ────────────────────────────────────────────────────────────
cat > .env <<EOF
APP_NAME=LCS Backend
APP_ENV=${APP_ENV}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
APP_TIMEZONE=America/Lima

APP_PORT=${APP_PORT}
WWWUSER=1000
WWWGROUP=1000

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=$([ "$APP_ENV" = "production" ] && echo "error" || echo "debug")

# ── Conexión por defecto ─────────────────────────────────────────────────────
DB_CONNECTION=dbsiaw

# ── Credenciales genéricas — usadas por docker-compose.yml para bootstrapear
#    el contenedor MariaDB (MYSQL_USER/MYSQL_PASSWORD/MYSQL_ROOT_PASSWORD) ────
DB_USERNAME=sail
DB_PASSWORD=${DB_PASSWORD}

# ── DB Negocio (tablas lcs_*) ────────────────────────────────────────────────
DB_LCS_HOST=${DB_LCS_HOST}
DB_LCS_PORT=3306
DB_LCS_DATABASE=db_lcs
DB_LCS_USERNAME=sail
DB_LCS_PASSWORD=${DB_PASSWORD}

# ── DB SIAW — usuarios, roles, permisos (tablas siaw_*) ─────────────────────
DB_SIAW_HOST=${DB_LCS_HOST}
DB_SIAW_PORT=3306
DB_SIAW_DATABASE=db_siaw
DB_SIAW_USERNAME=sail
DB_SIAW_PASSWORD=${DB_PASSWORD}

# ── DB Sincro — jobs, colas, cache, migrations ──────────────────────────────
DB_SINCRO_HOST=${DB_LCS_HOST}
DB_SINCRO_PORT=3306
DB_SINCRO_DATABASE=db_sincro
DB_SINCRO_USERNAME=sail
DB_SINCRO_PASSWORD=${DB_PASSWORD}

# ── Root password (solo para K8s secret) ────────────────────────────────────
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

REDIS_CLIENT=phpredis
REDIS_HOST=$([ "$APP_ENV" = "production" ] && echo "redis-service" || echo "lcs_redis")
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=$([ "$APP_ENV" = "production" ] && echo "localhost" || echo "lcs_mailpit")
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@lcs.com"
MAIL_FROM_NAME="\${APP_NAME}"

CORS_ALLOWED_ORIGINS=${CORS_ORIGINS}

VITE_APP_NAME="\${APP_NAME}"
VITE_PORT=5176

# ── Discord ──────────────────────────────────────────────────────────────────
DISCORD_WEBHOOK_URL=${DISCORD_WEBHOOK}
EOF

echo ""
echo -e "${GREEN}  ✓ .env creado correctamente.${NC}"
echo ""
echo -e "${BLUE}  ── Resumen ──────────────────────────────────────${NC}"
echo -e "  APP_ENV  : ${GREEN}${APP_ENV}${NC}"
echo -e "  APP_URL  : ${GREEN}${APP_URL}${NC}"
echo -e "  DB_HOST  : ${GREEN}${DB_LCS_HOST}${NC}"
echo ""
echo -e "${YELLOW}  Las claves RSA se generan al correr start.sh (php artisan auth:rotate-keys).${NC}"
echo -e "${YELLOW}  El script se eliminará ahora.${NC}"
echo ""

# Auto-eliminación
rm -f "$SCRIPT_PATH"
echo -e "${GREEN}  ✓ setup.sh eliminado. Ejecuta: bash start.sh${NC}"
