#!/bin/bash
set -uo pipefail

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

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

usage() {
    cat <<USAGE
Uso: ./start.sh [modo] [opciones]

Modos
  --up (default)   Levanta ESTE proyecto tal como está (tras clonarlo): .env,
                   contenedores, bases, migraciones, seeders, RSA y Swagger.
                   No pregunta nada; los puertos salen del .env.
  --new            Asistente para crear un proyecto NUEVO a partir de esta base
                   (SIAW + catálogo): pide nombre, prefijo y puertos, genera la
                   carpeta con todo renombrado + su .env, y lo levanta.

Opciones de --up
  -p, --port N       Puerto de la app / Swagger   (APP_PORT)
  -d, --db-port N    Puerto de la base de datos   (FORWARD_DB_PORT)
  -r, --rebuild      Fuerza build + up aunque ya esté levantado

Opciones de --new (las que no se pasen se preguntan; sin terminal usa defaults)
  --name "Gestión Hotelera"   Nombre del sistema
  --prefix agh                Prefijo (2-10 letras/números, empieza con letra)
  --dir RUTA                  Carpeta destino (default: ../<nombre-en-slug>)
  -p, --port N                Puerto app / Swagger
  -d, --db-port N             Puerto base de datos
  --redis-port N              Puerto Redis
  --mail-port N               Puerto dashboard Mailpit
  --cors URL                  Origen del frontend (CORS)
  --repo URL                  Repo remoto vacío del proyecto nuevo: se hace git init,
                              commit inicial y push (Enter/omitido = sin push)
  --no-up                     Solo genera el proyecto, no lo levanta
USAGE
}

MODE="up"
APP_PORT_ARG=""
DB_PORT_ARG=""
FORCE_REBUILD=0
NEW_NAME=""; NEW_PREFIX=""; NEW_DIR=""; NEW_REDIS_PORT=""; NEW_MAIL_PORT=""; NEW_CORS=""; NEW_REPO=""; NEW_UP=1
while [ $# -gt 0 ]; do
    case "$1" in
        --up)          MODE="up"; shift ;;
        --new)         MODE="new"; shift ;;
        -h|--help)     usage; exit 0 ;;
        -p|--port)     APP_PORT_ARG="${2:-}"; shift 2 || shift ;;
        --port=*)      APP_PORT_ARG="${1#--port=}"; shift ;;
        -d|--db-port)  DB_PORT_ARG="${2:-}"; shift 2 || shift ;;
        --db-port=*)   DB_PORT_ARG="${1#--db-port=}"; shift ;;
        -r|--rebuild)  FORCE_REBUILD=1; shift ;;
        --name)        NEW_NAME="${2:-}"; shift 2 || shift ;;
        --name=*)      NEW_NAME="${1#--name=}"; shift ;;
        --prefix)      NEW_PREFIX="${2:-}"; shift 2 || shift ;;
        --prefix=*)    NEW_PREFIX="${1#--prefix=}"; shift ;;
        --dir)         NEW_DIR="${2:-}"; shift 2 || shift ;;
        --dir=*)       NEW_DIR="${1#--dir=}"; shift ;;
        --redis-port)  NEW_REDIS_PORT="${2:-}"; shift 2 || shift ;;
        --redis-port=*) NEW_REDIS_PORT="${1#--redis-port=}"; shift ;;
        --mail-port)   NEW_MAIL_PORT="${2:-}"; shift 2 || shift ;;
        --mail-port=*) NEW_MAIL_PORT="${1#--mail-port=}"; shift ;;
        --cors)        NEW_CORS="${2:-}"; shift 2 || shift ;;
        --cors=*)      NEW_CORS="${1#--cors=}"; shift ;;
        --repo)        NEW_REPO="${2:-}"; shift 2 || shift ;;
        --repo=*)      NEW_REPO="${1#--repo=}"; shift ;;
        --no-up)       NEW_UP=0; shift ;;
        *)             usage >&2; fail "Argumento desconocido: $1" ;;
    esac
done

# set_env_var <archivo> <KEY> <valor>: reemplaza o agrega KEY=valor.
set_env_var() {
    local file="$1" key="$2" value="$3" esc
    esc="$(printf '%s' "$value" | sed -e 's/[\\|&]/\\&/g')"
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${esc}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
#  --new : crea un proyecto nuevo a partir de esta base
# ─────────────────────────────────────────────────────────────────────────────
# netstat una sola vez (en Windows cada /dev/tcp a un puerto cerrado tarda ~2s);
# si no hay netstat, se sondea con /dev/tcp.
LISTEN_CACHE=""
HAVE_NETSTAT=0
if command -v netstat >/dev/null 2>&1; then
    HAVE_NETSTAT=1
    LISTEN_CACHE="$(netstat -an 2>/dev/null | grep -i 'LISTEN')"
fi
port_in_use() {
    if [ "$HAVE_NETSTAT" = "1" ]; then
        printf '%s
' "$LISTEN_CACHE" | grep -Eq "[:.]$1[[:space:]]"
    else
        (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
    fi
}

# find_free_port <desde>: primer puerto libre (no escuchando ni ya elegido).
RESERVED_PORTS=" "
find_free_port() {
    local p="$1"
    while port_in_use "$p" || [[ "$RESERVED_PORTS" == *" $p "* ]]; do p=$((p + 1)); done
    echo "$p"
}

# ask <VAR> <etiqueta> <default>: si VAR ya viene por flag no pregunta.
ask() {
    local var="$1" label="$2" def="$3" ans=""
    [ -n "${!var}" ] && return 0
    if [ -t 0 ]; then read -r -p "${label}${def:+ [${def}]}: " ans; fi
    printf -v "$var" '%s' "${ans:-$def}"
}

# ask_port <VAR> <etiqueta> <desde>: pide un puerto libre y lo reserva.
ask_port() {
    local var="$1" label="$2" from="$3" def
    def="$(find_free_port "$from")"
    while true; do
        ask "$var" "Puerto de ${label}" "$def"
        if ! [[ "${!var}" =~ ^[0-9]+$ ]] || [ "${!var}" -lt 1 ] || [ "${!var}" -gt 65535 ]; then
            echo -e "${RED}  Puerto inválido: '${!var}'${NC}" >&2
        elif port_in_use "${!var}" || [[ "$RESERVED_PORTS" == *" ${!var} "* ]]; then
            echo -e "${RED}  El puerto ${!var} ya está en uso (sugerido: ${def}).${NC}" >&2
        else
            RESERVED_PORTS="${RESERVED_PORTS}${!var} "
            return 0
        fi
        [ -t 0 ] || fail "Puerto de ${label} no disponible."
        printf -v "$var" '%s' ""
    done
}

new_project() {
    [ -f .env.example ] && [ -f docker-compose.yml ] || fail "Ejecuta --new desde la raíz de la plantilla."

    local tpl tpl_cap tpl_up
    tpl="$(grep '^PROJECT_PREFIX=' .env.example | cut -d'=' -f2 | tr -d ' \r')"; tpl="${tpl:-lcs}"
    tpl_cap="$(echo "${tpl:0:1}" | tr '[:lower:]' '[:upper:]')${tpl:1}"
    tpl_up="$(echo "$tpl" | tr '[:lower:]' '[:upper:]')"

    echo -e "${BLUE}=========================================="
    echo -e "  Nuevo proyecto (base ${tpl_up}: SIAW + catálogo)"
    echo -e "==========================================${NC}"

    # Nombre del sistema (obligatorio)
    ask NEW_NAME "Nombre del sistema (ej. Gestión Hotelera)" ""
    [ -n "$NEW_NAME" ] || fail "El nombre del sistema es obligatorio (--name)."

    # Slug (carpeta) y prefijo sugerido (iniciales, o 3 primeras letras)
    local ascii slug suggest
    ascii="$(printf '%s' "$NEW_NAME" | sed 'y/áéíóúüñÁÉÍÓÚÜÑ/aeiouunAEIOUUN/')"
    slug="$(printf '%s' "$ascii" | tr '[:upper:]' '[:lower:]' | sed -e 's/[^a-z0-9]\{1,\}/-/g' -e 's/^-//' -e 's/-$//')"
    [ -n "$slug" ] || fail "El nombre debe contener letras o números."
    suggest="$(printf '%s' "$ascii" | tr '[:upper:]' '[:lower:]' | awk '{ n=split($0,w," "); s=""; if (n>1) { for(i=1;i<=n&&i<=5;i++) s=s substr(w[i],1,1) } else { s=substr(w[1],1,3) } print s }' | tr -cd 'a-z0-9')"

    ask NEW_PREFIX "Prefijo (tablas <prefijo>_*, conexión db<prefijo>, contenedores <prefijo>_*)" "$suggest"
    NEW_PREFIX="$(echo "$NEW_PREFIX" | tr '[:upper:]' '[:lower:]')"
    [[ "$NEW_PREFIX" =~ ^[a-z][a-z0-9]{1,9}$ ]] || fail "Prefijo inválido '${NEW_PREFIX}': 2-10 caracteres [a-z0-9], empezando con letra."
    case "$NEW_PREFIX" in
        siaw|sincro|db|app|laravel|"$tpl") fail "Prefijo '${NEW_PREFIX}' reservado: elige otro." ;;
    esac

    ask NEW_DIR "Carpeta destino" "$(dirname "$(pwd)")/${slug}"
    if [ -e "$NEW_DIR" ] && [ -n "$(ls -A "$NEW_DIR" 2>/dev/null)" ]; then
        fail "La carpeta '${NEW_DIR}' ya existe y no está vacía."
    fi

    echo -e "${BLUE}— Puertos de localhost (se sugiere el primero libre) —${NC}"
    ask_port APP_PORT_ARG   "la API / Swagger (/api/documentation)" 8844
    ask_port DB_PORT_ARG    "la base de datos"                      3322
    ask_port NEW_REDIS_PORT "Redis"                                 6381
    ask_port NEW_MAIL_PORT  "Mailpit (dashboard)"                   8026
    local mail_smtp vite_port
    mail_smtp="$(find_free_port 1026)"; RESERVED_PORTS="${RESERVED_PORTS}${mail_smtp} "
    vite_port="$(find_free_port 5176)"; RESERVED_PORTS="${RESERVED_PORTS}${vite_port} "
    ask NEW_CORS "Origen del frontend (CORS)" "http://localhost:4200"
    ask NEW_REPO "URL del repo remoto del proyecto nuevo (vacío = no subir)" ""

    local p_up p_cap
    p_cap="$(echo "${NEW_PREFIX:0:1}" | tr '[:lower:]' '[:upper:]')${NEW_PREFIX:1}"
    p_up="$(echo "$NEW_PREFIX" | tr '[:lower:]' '[:upper:]')"

    echo -e ""
    echo -e "  Sistema:   ${GREEN}${NEW_NAME}${NC}   prefijo: ${GREEN}${NEW_PREFIX}${NC}  (${p_cap} / ${p_up})"
    echo -e "  Carpeta:   ${GREEN}${NEW_DIR}${NC}"
    [ -n "$NEW_REPO" ] && echo -e "  Repo:      ${GREEN}${NEW_REPO}${NC}  (commit inicial + push a main)"
    echo -e "  Puertos:   API ${GREEN}${APP_PORT_ARG}${NC} · BD ${GREEN}${DB_PORT_ARG}${NC} · Redis ${GREEN}${NEW_REDIS_PORT}${NC} · Mailpit ${GREEN}${NEW_MAIL_PORT}${NC}"
    if [ -t 0 ]; then
        local ok; read -r -p "¿Crear el proyecto? [S/n]: " ok
        case "$ok" in n|N|no|NO) fail "Cancelado." ;; esac
    fi

    # 1. Copiar la base (sin vendor/, .env, claves ni .git)
    echo -e "${GREEN}>>> Copiando la base...${NC}"
    mkdir -p "$NEW_DIR" || fail "No se pudo crear ${NEW_DIR}."
    NEW_DIR="$(cd "$NEW_DIR" && pwd)"
    if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git ls-files -co --exclude-standard -z             | tar --null --ignore-failed-read -T - -cf - 2>/dev/null | tar -xf - -C "$NEW_DIR"
    else
        tar --exclude=./vendor --exclude=./.git --exclude=./.env --exclude=./node_modules \
            --exclude=./storage/keys --exclude=./storage/logs --exclude=./storage/api-docs -cf - . \
            | tar -xf - -C "$NEW_DIR"
    fi
    [ -f "$NEW_DIR/artisan" ] || fail "La copia falló (no se encontró artisan en ${NEW_DIR})."

    # 2. Renombrar contenido y rutas: lcs→x, Lcs→X, LCS→X. start.sh queda
    #    intacto porque deriva los nombres de PROJECT_PREFIX (.env).
    echo -e "${GREEN}>>> Renombrando ${tpl} → ${NEW_PREFIX} (contenido y rutas)...${NC}"
    local name_esc path dir base nb
    name_esc="$(printf '%s' "$NEW_NAME" | sed -e 's/[\\|&]/\\&/g')"
    grep -rIliZ --exclude-dir=.git --exclude=start.sh "$tpl" "$NEW_DIR"         | xargs -0 -r sed -i -e "s|${tpl_up} Backend|${name_esc} Backend|g"               -e "s|${tpl}|${NEW_PREFIX}|g" -e "s|${tpl_cap}|${p_cap}|g" -e "s|${tpl_up}|${p_up}|g"

    while IFS= read -r -d '' path; do
        dir="$(dirname "$path")"; base="$(basename "$path")"
        nb="$(printf '%s' "$base" | sed -e "s|${tpl}|${NEW_PREFIX}|g" -e "s|${tpl_cap}|${p_cap}|g" -e "s|${tpl_up}|${p_up}|g")"
        [ "$nb" = "$base" ] || mv "$path" "$dir/$nb"
    done < <(find "$NEW_DIR" -depth \( -name "*${tpl}*" -o -name "*${tpl_cap}*" -o -name "*${tpl_up}*" \) ! -path "$NEW_DIR/.git/*" -print0)

    # 3. .env propio con los puertos elegidos
    echo -e "${GREEN}>>> Generando .env...${NC}"
    cp "$NEW_DIR/.env.example" "$NEW_DIR/.env"
    local envf="$NEW_DIR/.env"
    set_env_var "$envf" PROJECT_PREFIX "$NEW_PREFIX"
    set_env_var "$envf" APP_PORT "$APP_PORT_ARG"
    set_env_var "$envf" FORWARD_DB_PORT "$DB_PORT_ARG"
    set_env_var "$envf" FORWARD_REDIS_PORT "$NEW_REDIS_PORT"
    set_env_var "$envf" FORWARD_MAILPIT_DASHBOARD_PORT "$NEW_MAIL_PORT"
    set_env_var "$envf" FORWARD_MAILPIT_PORT "$mail_smtp"
    set_env_var "$envf" VITE_PORT "$vite_port"
    set_env_var "$envf" L5_SWAGGER_CONST_HOST "http://localhost:${APP_PORT_ARG}"
    set_env_var "$envf" CORS_ALLOWED_ORIGINS "$NEW_CORS"

    # 4. Git: repo propio. Con URL: commit inicial + push (el .env y vendor/ están en .gitignore).
    if ( cd "$NEW_DIR" && git init -q -b main ); then
        if [ -n "$NEW_REPO" ]; then
            echo -e "${GREEN}>>> Subiendo a ${NEW_REPO}...${NC}"
            (
                cd "$NEW_DIR" || exit 1
                git remote add origin "$NEW_REPO"                     && git add -A                     && git commit -q -m "chore: base ${NEW_NAME} (SIAW + catálogo ${p_up}) desde plantilla ${tpl_up}"                     && git push -u origin main
            ) || echo -e "${YELLOW}⚠ No se pudo hacer push (¿repo no vacío / sin credenciales / sin git user?). El proyecto quedó listo; súbelo a mano desde ${NEW_DIR}.${NC}"
        fi
    else
        echo -e "${YELLOW}⚠ No se pudo inicializar git en ${NEW_DIR}.${NC}"
    fi
    echo -e "${GREEN}✓ Proyecto '${NEW_NAME}' creado en ${NEW_DIR}${NC}"

    # 5. Levantar el proyecto nuevo (mismo start.sh, modo --up)
    local up_rc=0
    if [ "$NEW_UP" = "1" ]; then
        ( cd "$NEW_DIR" && bash ./start.sh --up ) || up_rc=$?
    fi

    # 6. Secrets de producción: archivo local (gitignored) + bloque para copiar y pegar.
    write_secrets_file "$NEW_DIR" "$NEW_PREFIX" "$NEW_REPO" "$NEW_CORS"
    echo -e ""
    echo -e "${BLUE}==========================================${NC}"
    if [ "$up_rc" -ne 0 ]; then
        echo -e "${RED}✗ El proyecto se creó pero el levantado falló (código ${up_rc}). Revisa el log y reintenta:${NC}"
        echo -e "  ${YELLOW}cd \"${NEW_DIR}\" && ./start.sh --up${NC}"
    elif [ "$NEW_UP" = "0" ]; then
        echo -e "  Para levantarlo:  ${YELLOW}cd \"${NEW_DIR}\" && ./start.sh --up${NC}"
    fi
    echo -e "${GREEN}  Secrets de producción (generados en ${NEW_DIR}/secrets-prod.sh — gitignored):${NC}"
    echo -e "${BLUE}==========================================${NC}"
    cat "$NEW_DIR/secrets-prod.sh"
    echo -e "${BLUE}==========================================${NC}"
    echo -e "  Probar todos los CRUD:  ${YELLOW}cd \"${NEW_DIR}\" && python tests/api_test.py --txt -v${NC}"
    exit "$up_rc"
}

# write_secrets_file <dir> <prefijo> <repo> <cors>: genera secrets-prod.sh con todo lo que se
# puede resolver solo (passwords de BD aleatorias, APP_KEY, admin inicial, nombres del
# prefijo) y deja como variables a rellenar lo que solo vos tenés (kubeconfig, PAT, Resend...).
rand_secret() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "${1:-24}"; }
write_secrets_file() {
    local dir="$1" p="$2" repo_url="$3" cors="$4" file="$1/secrets-prod.sh"
    local slug app_key admin_pass db_pass root_pass db_user
    slug="$(printf '%s' "$repo_url" | sed -nE 's#.*github\.com[:/]([^/]+/[^/]+)$#\1#p' | sed -E 's#\.git$##')"
    app_key="$(grep '^APP_KEY=' "$dir/.env" | cut -d'=' -f2- | tr -d '\r')"
    admin_pass="$(rand_secret 16)Aa1!"
    db_pass="$(rand_secret 28)"; root_pass="$(rand_secret 28)"; db_user="${p}_user"
    [ -n "$app_key" ] || app_key="<ejecuta: docker compose exec ${p}_laravel_backend php artisan key:generate --show>"

    cat > "$file" <<SECRETS
#!/bin/bash
# Generado por start.sh --new. Contiene passwords: NO se sube a git (.gitignore).
# 1) Completa SOLO el bloque "RELLENAR".  2) bash secrets-prod.sh
# Requiere: gh (GitHub CLI, 'gh auth login') y kubectl con el kubeconfig del cluster de destino.
set -euo pipefail

# ── RELLENAR (lo único que no se puede generar) ─────────────────────────────
REPO="${slug:-<owner/repo>}"
KUBECONFIG_FILE="<ruta al kubeconfig del cluster, ej. ~/.kube/prod.yaml>"
GH_USER="<usuario de GitHub>"
GHCR_PAT="<PAT con read:packages>"
FRONTEND_URL="${cors}"
MAIL_DOMAIN="<tudominio.com>"
RESEND_API_KEY="<re_xxx>"
ADMIN_EMAIL="<tu email real: recibe la password temporal de los usuarios>"
DISCORD_WEBHOOK_URL=""   # opcional

for v in REPO KUBECONFIG_FILE GH_USER GHCR_PAT MAIL_DOMAIN RESEND_API_KEY ADMIN_EMAIL; do
    if [[ "\${!v}" == "<"* ]]; then echo "Falta completar \$v" >&2; exit 1; fi
done

# ── GitHub Actions ──────────────────────────────────────────────────────────
gh secret set KUBECONFIG_PROD -R "\$REPO" < "\${KUBECONFIG_FILE/#\~/\$HOME}"
[ -n "\$DISCORD_WEBHOOK_URL" ] && gh secret set DISCORD_WEBHOOK_URL -R "\$REPO" --body "\$DISCORD_WEBHOOK_URL"

# ── Cluster K3s (namespace ${p}-prod) ───────────────────────────────────────
export KUBECONFIG="\${KUBECONFIG_FILE/#\~/\$HOME}"
NS=${p}-prod
kubectl create namespace \$NS --dry-run=client -o yaml | kubectl apply -f -

kubectl -n \$NS create secret docker-registry ghcr-secret \\
  --docker-server=ghcr.io --docker-username="\$GH_USER" --docker-password="\$GHCR_PAT" \\
  --docker-email="no-reply@\$MAIL_DOMAIN" --dry-run=client -o yaml | kubectl apply -f -

kubectl -n \$NS create secret generic ${p}-db-root \\
  '--from-literal=DB_ROOT_PASSWORD=${root_pass}' --dry-run=client -o yaml | kubectl apply -f -

kubectl -n \$NS create secret generic ${p}-env \\
  '--from-literal=APP_KEY=${app_key}' \\
  '--from-literal=DB_CONNECTION=dbsiaw' \\
  '--from-literal=DB_${p^^}_HOST=mariadb-service' '--from-literal=DB_${p^^}_USERNAME=${db_user}' '--from-literal=DB_${p^^}_PASSWORD=${db_pass}' '--from-literal=DB_${p^^}_DATABASE=db_${p}' \\
  '--from-literal=DB_SIAW_HOST=mariadb-service' '--from-literal=DB_SIAW_USERNAME=${db_user}' '--from-literal=DB_SIAW_PASSWORD=${db_pass}' '--from-literal=DB_SIAW_DATABASE=db_siaw' \\
  '--from-literal=DB_SINCRO_HOST=mariadb-service' '--from-literal=DB_SINCRO_USERNAME=${db_user}' '--from-literal=DB_SINCRO_PASSWORD=${db_pass}' '--from-literal=DB_SINCRO_DATABASE=db_sincro' \\
  '--from-literal=REDIS_HOST=redis-service' '--from-literal=REDIS_PORT=6379' '--from-literal=REDIS_CLIENT=phpredis' \\
  '--from-literal=CACHE_STORE=redis' '--from-literal=DB_CACHE_CONNECTION=dbsincro' \\
  '--from-literal=QUEUE_CONNECTION=database' '--from-literal=DB_QUEUE_CONNECTION=dbsincro' \\
  '--from-literal=SESSION_DRIVER=database' '--from-literal=SESSION_CONNECTION=dbsincro' '--from-literal=SESSION_LIFETIME=120' \\
  '--from-literal=LOG_CHANNEL=stack' '--from-literal=LOG_STACK=daily' '--from-literal=LOG_DAILY_DAYS=14' \\
  "--from-literal=CORS_ALLOWED_ORIGINS=\$FRONTEND_URL" \\
  '--from-literal=MAIL_MAILER=resend' "--from-literal=RESEND_API_KEY=\$RESEND_API_KEY" "--from-literal=MAIL_FROM_ADDRESS=no-reply@\$MAIL_DOMAIN" \\
  "--from-literal=SIAW_ADMIN_EMAIL_INICIAL=\$ADMIN_EMAIL" '--from-literal=SIAW_ADMIN_PASSWORD_INICIAL=${admin_pass}' \\
  --dry-run=client -o yaml | kubectl apply -f -

echo "✓ Secrets aplicados. Admin inicial de producción: \$ADMIN_EMAIL / ${admin_pass}"
echo "  Siguiente: activar el trigger 'push a main' en .github/workflows/deploy.yml (docs/CONFIGURAR_SECRETS.md §6)."
SECRETS
    chmod +x "$file" 2>/dev/null || true
}

if [ "$MODE" = "new" ]; then
    new_project
fi

# ─────────────────────────────────────────────────────────────────────────────
#  --up : levanta este proyecto tal como está
# ─────────────────────────────────────────────────────────────────────────────
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
ensure_env_var PROJECT_PREFIX "lcs"
PREFIX="$(grep '^PROJECT_PREFIX=' .env | cut -d'=' -f2 | tr -d ' \r')"
PREFIX_UP="$(echo "$PREFIX" | tr '[:lower:]' '[:upper:]')"
PREFIX_CAP="$(echo "${PREFIX:0:1}" | tr '[:lower:]' '[:upper:]')${PREFIX:1}"
APP_CONTAINER="${PREFIX}_laravel_backend"

echo -e "${BLUE}=========================================="
echo -e "  ${PREFIX_UP} Backend — Inicio de Desarrollo"
echo -e "==========================================${NC}"

ensure_env_var WWWUSER "$(id -u 2>/dev/null || echo 1000)"
ensure_env_var WWWGROUP "$(id -g 2>/dev/null || echo 1000)"
# "sail" hardcodeado a propósito: database/docker-init/01_init_databases.sql
# otorga privilegios a ese usuario; si difiere, los GRANT fallan.
ensure_env_var DB_USERNAME "sail"
ensure_env_var L5_SWAGGER_GENERATE_ALWAYS "true"
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
# --up no pregunta: usa los del .env, o los de -p/--port y -d/--db-port.
# choose_port <VAR_ENV> <valor_por_defecto> <descripcion> <valor_pedido>
# Resuelve el puerto (argumento > valor actual del
# .env > default), lo valida, lo guarda en .env y lo deja en $CHOSEN_PORT.
choose_port() {
    local key="$1" default="$2" label="$3" port="$4" current
    current="$(grep "^${key}=" .env | cut -d'=' -f2 | tr -d ' \r')"
    current="${current:-$default}"
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
# Swagger arma las URLs de "Try it out" con L5_SWAGGER_CONST_HOST: si queda en
# otro puerto que APP_PORT, el login (y todo) falla con "Failed to fetch".
SWAGGER_HOST="http://localhost:${CHOSEN_PORT}"
if grep -q "^L5_SWAGGER_CONST_HOST=" .env; then
    sed -i "s|^L5_SWAGGER_CONST_HOST=.*|L5_SWAGGER_CONST_HOST=${SWAGGER_HOST}|" .env
else
    printf '%s=%s
' L5_SWAGGER_CONST_HOST "$SWAGGER_HOST" >> .env
fi
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

# 3-4. Build + up solo si hace falta. Si los contenedores ya están corriendo
# con los puertos del .env, se omite (re-ejecutar el script no relevanta nada).
# Se levanta de nuevo si: falta algún contenedor, cambió un puerto, o se pasó
# -r/--rebuild.
ALREADY_UP=1
for svc in ${PREFIX}_laravel_backend ${PREFIX}_queue ${PREFIX}_scheduler ${PREFIX}_db ${PREFIX}_redis; do
    [ "$(docker inspect -f '{{.State.Running}}' "$svc" 2>/dev/null || echo false)" = "true" ] || ALREADY_UP=0
done
if [ "$ALREADY_UP" = "1" ]; then
    want_app="$(grep '^APP_PORT=' .env | cut -d'=' -f2 | tr -d ' 
')"
    want_db="$(grep '^FORWARD_DB_PORT=' .env | cut -d'=' -f2 | tr -d ' 
')"
    have_app="$(docker compose port ${PREFIX}_laravel_backend 80 2>/dev/null | awk -F: '{print $NF}')"
    have_db="$(docker compose port ${PREFIX}_db 3306 2>/dev/null | awk -F: '{print $NF}')"
    [ "$have_app" = "$want_app" ] && [ "$have_db" = "$want_db" ] || ALREADY_UP=0
fi

if [ "$ALREADY_UP" = "1" ] && [ "$FORCE_REBUILD" = "0" ]; then
    echo -e "${YELLOW}>>> Contenedores ya levantados — se omite build/up (usa -r para forzar).${NC}"
else
    echo -e "${GREEN}>>> Construyendo imagen base Laravel...${NC}"
    run_or_fail "El build de la imagen Docker falló. Revisa el log de arriba (docker compose build)."         docker compose build --pull ${PREFIX}_laravel_backend

    echo -e "${GREEN}>>> Levantando contenedores Docker...${NC}"
    run_or_fail "docker compose up -d falló."         docker compose up -d
fi

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

# 4c. PHP2Shell (CVE-2026-109618 / CVE-2026-109619): RCE corregido en PHP 8.4.27+.
PHP_MIN="8.4.27"
PHP_ACTUAL="$(docker exec "$APP_CONTAINER" php -r 'echo PHP_VERSION;' 2>/dev/null | tr -d '')"
if [ -n "$PHP_ACTUAL" ] && [ "$(printf '%s
%s
' "$PHP_MIN" "$PHP_ACTUAL" | sort -V | head -n1)" != "$PHP_MIN" ]; then
    fail "PHP ${PHP_ACTUAL} es vulnerable (CVE-2026-109618/109619). Reconstruye sin cache: docker compose build --pull --no-cache ${PREFIX}_laravel_backend"
fi
echo -e "${GREEN}>>> PHP ${PHP_ACTUAL:-desconocido} (mínimo seguro ${PHP_MIN})${NC}"

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
        docker compose exec -T ${PREFIX}_db mysql -uroot -p"${DB_PASSWORD_VAL}" -e \
            "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${DB_USERNAME_VAL}'@'%';"
done
docker compose exec -T ${PREFIX}_db mysql -uroot -p"${DB_PASSWORD_VAL}" -e "FLUSH PRIVILEGES;"

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
# Solo se cachea en producción: en desarrollo la caché de rutas/config hace que
# los controllers/rutas nuevos y los cambios de .env no se vean sin route:clear.
APP_ENV_VAL="$(grep '^APP_ENV=' .env | cut -d'=' -f2 | tr -d ' \r')"
if [ "$APP_ENV_VAL" = "production" ]; then
    echo -e "${GREEN}>>> Optimizando configuración y rutas...${NC}"
    docker compose exec -T "$APP_CONTAINER" php artisan config:cache 2>/dev/null || true
    docker compose exec -T "$APP_CONTAINER" php artisan route:cache  2>/dev/null || true
else
    echo -e "${GREEN}>>> Limpiando cachés (entorno ${APP_ENV_VAL:-local}: rutas y config dinámicas)...${NC}"
    docker compose exec -T "$APP_CONTAINER" php artisan optimize:clear 2>/dev/null || true
fi

# 16. Mostrar puertos desde .env
APP_PORT=$(grep "^APP_PORT=" .env | cut -d'=' -f2 | tr -d ' '); APP_PORT=${APP_PORT:-8844}
DB_PORT=$(grep "^FORWARD_DB_PORT=" .env | cut -d'=' -f2 | tr -d ' '); DB_PORT=${DB_PORT:-3322}
MAIL_PORT=$(grep "^FORWARD_MAILPIT_DASHBOARD_PORT=" .env | cut -d'=' -f2 | tr -d ' '); MAIL_PORT=${MAIL_PORT:-8026}

echo -e "${BLUE}=========================================="
echo -e "  ✓ ${PREFIX_UP} BACKEND INICIADO CORRECTAMENTE"
echo -e "${NC}"
echo -e "  Aplicación:        ${GREEN}http://localhost:${APP_PORT}${NC}"
echo -e "  Swagger API:       ${GREEN}http://localhost:${APP_PORT}/api/documentation${NC}"
echo -e "  Base de datos:     ${GREEN}localhost:${DB_PORT}${NC}  (${PREFIX}_db)"
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
echo -e "  Probar los CRUD:   ${YELLOW}python tests/api_test.py --txt -v${NC}  (todos los modelos)"
echo -e ""
echo -e "  Contenedores activos:"
echo -e "  ${YELLOW}${PREFIX}_laravel_backend${NC}  → HTTP API"
echo -e "  ${YELLOW}${PREFIX}_queue${NC}            → queue:work (jobs)"
echo -e "  ${YELLOW}${PREFIX}_scheduler${NC}        → schedule:work (tareas programadas)"
echo -e ""
echo -e "  Comandos artisan (desde host) — ejemplo al agregar el primer módulo de negocio:"
echo -e "  ${YELLOW}docker compose exec ${PREFIX}_laravel_backend php artisan make:migration create_${PREFIX}_xxx_table${NC}"
echo -e "  ${YELLOW}docker compose exec ${PREFIX}_laravel_backend php artisan make:model Models/db${PREFIX}/${PREFIX_CAP}Xxx${NC}"
echo -e "  ${YELLOW}docker compose exec ${PREFIX}_laravel_backend php artisan make:controller Api/${PREFIX_CAP}XxxController${NC}"
echo -e "  ${YELLOW}docker compose exec ${PREFIX}_laravel_backend php artisan migrate --path=database/migrations/{Modulo} --database=db${PREFIX} --force${NC}  (negocio propio — se auto-detecta al tener carpeta)"
echo -e "${BLUE}==========================================${NC}"
