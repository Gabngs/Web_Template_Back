#!/bin/bash

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${BLUE}=========================================="
echo -e "  Web Template Back - Setup"
echo -e "==========================================${NC}"

# ─── MODO DE USO ──────────────────────────────────────────────────────────────
echo -e ""
echo -e "${CYAN}¿Qué estás haciendo?${NC}"
echo -e "  ${GREEN}1)${NC} Instalar en otra PC (mismo proyecto, solo levantar el entorno)"
echo -e "  ${GREEN}2)${NC} Clonar como base para un NUEVO proyecto"
echo -e ""
read -rp "Selecciona una opción [1/2]: " MODE

if [[ "$MODE" == "2" ]]; then
    echo -e ""
    echo -e "${CYAN}=== Configuración del nuevo proyecto ===${NC}"

    # Nombre del proyecto
    read -rp "Nombre del proyecto (ej: MiCliente_Back): " NEW_PROJECT_NAME
    NEW_PROJECT_NAME="${NEW_PROJECT_NAME:-Web_Template_Back}"

    # Nombre de la base de datos
    read -rp "Nombre de la base de datos (ej: mi_cliente_db): " NEW_DB_NAME
    NEW_DB_NAME="${NEW_DB_NAME:-web_template_db}"

    # Email del admin inicial
    read -rp "Email del administrador inicial (ej: admin@micliente.com): " NEW_ADMIN_EMAIL
    NEW_ADMIN_EMAIL="${NEW_ADMIN_EMAIL:-admin@template.com}"

    # Email from para correos
    read -rp "Email remitente para notificaciones (ej: noreply@micliente.com): " NEW_MAIL_FROM
    NEW_MAIL_FROM="${NEW_MAIL_FROM:-noreply@example.com}"

    # Puerto de la app
    read -rp "Puerto de la aplicación [default: 8888]: " NEW_APP_PORT
    NEW_APP_PORT="${NEW_APP_PORT:-8888}"

    # Puerto de la base de datos (forward)
    read -rp "Puerto forward MariaDB [default: 3320]: " NEW_DB_PORT
    NEW_DB_PORT="${NEW_DB_PORT:-3320}"

    echo -e ""
    echo -e "${YELLOW}>>> Configurando nuevo proyecto: ${GREEN}${NEW_PROJECT_NAME}${NC}"

    # ── Crear .env desde .env.example ─────────────────────────────────────────
    cp .env.example .env

    # Aplicar valores personalizados al .env
    sed -i "s|APP_NAME=.*|APP_NAME=\"${NEW_PROJECT_NAME}\"|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=${NEW_DB_NAME}|" .env
    sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=\"${NEW_MAIL_FROM}\"|" .env
    sed -i "s|APP_PORT=.*|APP_PORT=${NEW_APP_PORT}|" .env
    sed -i "s|FORWARD_DB_PORT=.*|FORWARD_DB_PORT=${NEW_DB_PORT}|" .env
    sed -i "s|L5_SWAGGER_CONST_HOST=.*|L5_SWAGGER_CONST_HOST=http://localhost:${NEW_APP_PORT}|" .env

    # ── Actualizar nombre del servicio en compose.yaml ─────────────────────────
    SAFE_NAME=$(echo "$NEW_PROJECT_NAME" | tr ' ' '_')
    sed -i "s|Web_Backend:|${SAFE_NAME}:|g" compose.yaml
    sed -i "s|SERVICE=\"Web_Backend\"|SERVICE=\"${SAFE_NAME}\"|" start.sh

    # ── Actualizar email del admin en el seeder ────────────────────────────────
    if [ -f "database/seeders/AdminUserSeeder.php" ]; then
        sed -i "s|admin@template\.com|${NEW_ADMIN_EMAIL}|g" database/seeders/AdminUserSeeder.php
        echo -e "${GREEN}  ✓ AdminUserSeeder actualizado: ${NEW_ADMIN_EMAIL}${NC}"
    fi

    echo -e "${GREEN}  ✓ .env configurado${NC}"
    echo -e "${GREEN}  ✓ compose.yaml actualizado${NC}"
    echo -e ""
    echo -e "${YELLOW}  SIGUIENTE PASO: Configura SANCTUM_TOKEN_EXPIRATION y SWAGGER_PASSWORD en .env${NC}"
    echo -e ""

else
    # Modo 1: instalación en otra PC del mismo proyecto
    if [ ! -f .env ]; then
        echo -e "${YELLOW}>>> Creando .env desde .env.example...${NC}"
        cp .env.example .env
    fi
fi

# ─── NOMBRE DEL SERVICIO EN docker-compose.yml ───────────────────────────────
# Toma el primer servicio definido bajo la clave "services:" (segunda línea con ese patrón)
SERVICE=$(awk '/^services:/{found=1; next} found && /^  [A-Za-z_][A-Za-z0-9_]*:/{print $1; exit}' compose.yaml | tr -d ':')
SERVICE="${SERVICE:-Web_Backend}"

echo -e "${GREEN}>>> Servicio Docker: ${CYAN}${SERVICE}${NC}"

# ─── 1. Levantar contenedores ─────────────────────────────────────────────────
echo -e "${GREEN}>>> Levantando contenedores...${NC}"
docker compose up -d

# ─── 2. Instalar vendor si no existe ──────────────────────────────────────────
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}>>> Instalando dependencias composer (primera vez)...${NC}"
    docker run --rm \
      -v "/$(pwd):/var/www/html" \
      -w "//var/www/html" \
      laravelsail/php84-composer:latest \
      composer install --ignore-platform-reqs
fi

# ─── 3. Esperar servicios ─────────────────────────────────────────────────────
echo -e "${GREEN}>>> Esperando que los servicios estén listos (10s)...${NC}"
sleep 10

# ─── 4. Crear directorios necesarios ─────────────────────────────────────────
echo -e "${GREEN}>>> Verificando directorios de storage...${NC}"
docker compose exec -T "$SERVICE" bash -c "
    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/framework/testing
    mkdir -p storage/api-docs
    mkdir -p storage/logs
    mkdir -p storage/app/public
    mkdir -p bootstrap/cache
    mkdir -p resources/views/vendor/l5-swagger
"

# ─── 5. Permisos ──────────────────────────────────────────────────────────────
echo -e "${GREEN}>>> Ajustando permisos...${NC}"
docker compose exec -T "$SERVICE" bash -c "
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    chmod -R 777 storage bootstrap/cache storage/api-docs
"

# ─── 6. Limpiar caché ─────────────────────────────────────────────────────────
echo -e "${GREEN}>>> Limpiando caché...${NC}"
docker compose exec -T "$SERVICE" php artisan cache:clear 2>/dev/null || true
docker compose exec -T "$SERVICE" php artisan config:clear 2>/dev/null || true
docker compose exec -T "$SERVICE" php artisan view:clear  2>/dev/null || true
docker compose exec -T "$SERVICE" php artisan route:clear 2>/dev/null || true

# ─── 7. App Key ───────────────────────────────────────────────────────────────
echo -e "${GREEN}>>> Verificando App Key...${NC}"
APP_KEY_VAL=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
if [ -z "$APP_KEY_VAL" ] || [ "$APP_KEY_VAL" = '""' ]; then
    docker compose exec -T "$SERVICE" php artisan key:generate
fi

# ─── 8. Dependencias dentro del contenedor ───────────────────────────────────
echo -e "${GREEN}>>> Verificando dependencias...${NC}"
docker compose exec -T "$SERVICE" composer install --ignore-platform-reqs

# ─── 9. Publicar providers ───────────────────────────────────────────────────
echo -e "${GREEN}>>> Publicando Sanctum y Swagger...${NC}"
docker compose exec -T "$SERVICE" php artisan vendor:publish \
    --provider="Laravel\Sanctum\SanctumServiceProvider" --force 2>/dev/null || true
docker compose exec -T "$SERVICE" php artisan vendor:publish \
    --provider="L5Swagger\L5SwaggerServiceProvider" --force 2>/dev/null || true

# ─── 10. Migraciones ──────────────────────────────────────────────────────────
echo -e "${GREEN}>>> Ejecutando migraciones...${NC}"
docker compose exec -T "$SERVICE" php artisan migrate --force

# ─── 11. Seeders (solo en modo nuevo proyecto) ────────────────────────────────
if [[ "$MODE" == "2" ]]; then
    echo -e "${GREEN}>>> Ejecutando seeders iniciales...${NC}"
    docker compose exec -T "$SERVICE" php artisan db:seed --force
fi

# ─── 12. Generar Swagger ──────────────────────────────────────────────────────
echo -e "${GREEN}>>> Generando documentación Swagger...${NC}"
docker compose exec -T "$SERVICE" php artisan l5-swagger:generate 2>/dev/null || true

if docker compose exec -T "$SERVICE" test -f storage/api-docs/api-docs.json; then
    echo -e "${GREEN}  ✓ Swagger generado correctamente${NC}"
else
    echo -e "${RED}  ⚠ No se generó api-docs.json - revisa las anotaciones @OA${NC}"
fi

# ─── 13. Optimizar ────────────────────────────────────────────────────────────
echo -e "${GREEN}>>> Optimizando...${NC}"
docker compose exec -T "$SERVICE" php artisan config:cache 2>/dev/null || true
docker compose exec -T "$SERVICE" php artisan route:cache  2>/dev/null || true

# ─── Resumen final ────────────────────────────────────────────────────────────
APP_PORT=$(grep "^APP_PORT=" .env | cut -d'=' -f2 | tr -d ' ')
FWD_DB_PORT=$(grep "^FORWARD_DB_PORT=" .env | cut -d'=' -f2 | tr -d ' ')
APP_PORT="${APP_PORT:-8888}"
FWD_DB_PORT="${FWD_DB_PORT:-3320}"

echo -e ""
echo -e "${BLUE}=========================================="
echo -e "  ✓ PROYECTO LISTO"
echo -e "  App:      ${GREEN}http://localhost:${APP_PORT}${NC}"
echo -e "  Swagger:  ${GREEN}http://localhost:${APP_PORT}/api/documentation${NC}"
echo -e "  MariaDB:  ${GREEN}localhost:${FWD_DB_PORT}${NC}"
if [[ "$MODE" == "2" ]]; then
    echo -e ""
    echo -e "  ${YELLOW}Credenciales admin:${NC}"
    echo -e "  Email:    ${CYAN}${NEW_ADMIN_EMAIL:-admin@template.com}${NC}"
    echo -e "  Password: ${CYAN}password${NC}  ← ${RED}CAMBIAR EN PRODUCCIÓN${NC}"
fi
echo -e "${BLUE}==========================================${NC}"
