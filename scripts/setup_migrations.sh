#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# setup_migrations.sh
# Crea las migraciones base del template usando php artisan make:migration.
# Ejecutar UNA SOLA VEZ después de levantar el proyecto con start.sh.
#
# Uso:
#   bash scripts/setup_migrations.sh
# ─────────────────────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Detectar nombre del servicio desde compose.yaml
SERVICE=$(grep -m1 '^\s*[A-Za-z_][A-Za-z0-9_]*:$' compose.yaml | tr -d ' :' | head -1)
SERVICE="${SERVICE:-Web_Backend}"

echo -e "${YELLOW}Servicio: ${SERVICE}${NC}"
echo ""

# ─── Función helper ───────────────────────────────────────────────────────────
make_migration() {
    local name="$1"
    echo -e "${GREEN}>>> php artisan make:migration ${name}${NC}"
    docker compose exec -T "$SERVICE" php artisan make:migration "$name"
}

# ─── Migraciones adicionales al template base ─────────────────────────────────
# Solo se crean si no existe ya una migración con ese nombre

echo "Creando migraciones base del template..."
echo ""

# Agrega rol_id y deleted_at a users (que ya existe de Laravel)
make_migration "add_rol_id_deleted_at_to_users_table"

# Agrega campos de auditoría a roles
make_migration "add_audit_fields_to_roles_table"

# Tabla de metadata de modelos para el sistema de permisos
make_migration "create_content_model_table"

# Agrega content_model_id y auditoría a permissions
make_migration "add_content_model_id_to_permissions_table"

# Tabla pivot de permisos específicos por usuario
make_migration "create_user_permission_table"

echo ""
echo -e "${YELLOW}>>> Migraciones creadas. Ahora debes editar cada archivo generado.${NC}"
echo -e "${YELLOW}    Ver PROJECT_CONFIGURATION_MAP.md para el contenido esperado.${NC}"
echo ""
echo -e "${GREEN}>>> Ejecutando migraciones...${NC}"
docker compose exec -T "$SERVICE" php artisan migrate
