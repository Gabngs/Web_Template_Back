#!/usr/bin/env bash
# Rota la contraseña de un usuario de BD (o de redis) en lcs-prod:
#   1. genera una clave nueva
#   2. la aplica en MariaDB (ALTER USER) como root  — o en redis (CONFIG SET)
#   3. actualiza el/los Secret(s) de k8s
#   4. reinicia los pods que la usan
#   5. anexa la clave nueva a .secrets/credenciales-bd.md (en ESTA máquina)
#
# Requiere: kubectl con contexto de prod, jq, openssl.
# Uso:
#   ./k8s/scripts/rotate-db-passwords.sh lcs_app
#   ./k8s/scripts/rotate-db-passwords.sh lcs_migrate
#   ./k8s/scripts/rotate-db-passwords.sh redis
set -euo pipefail

NS="${NS:-lcs-prod}"
TARGET="${1:-}"
SECRETS_MD="$(cd "$(dirname "$0")/../.." && pwd)/.secrets/credenciales-bd.md"

[ -z "$TARGET" ] && { echo "uso: $0 {lcs_app|lcs_migrate|redis}"; exit 1; }
command -v jq >/dev/null || { echo "falta jq"; exit 1; }

NEWPW="$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)"
ROOT="$(kubectl -n "$NS" get secret lcs-db-root -o jsonpath='{.data.DB_ROOT_PASSWORD}' 2>/dev/null | base64 -d || true)"
[ -z "$ROOT" ] && ROOT="$(kubectl -n "$NS" get secret lcs-env -o jsonpath='{.data.DB_ROOT_PASSWORD}' | base64 -d)"

patch_secret() { # $1=secret $2=key $3=value
  kubectl -n "$NS" patch secret "$1" --type=merge \
    -p "{\"data\":{\"$2\":\"$(printf '%s' "$3" | base64 -w0)\"}}"
}

case "$TARGET" in
  lcs_app)
    kubectl -n "$NS" exec statefulset/mariadb -- mariadb -uroot -p"$ROOT" \
      -e "ALTER USER 'lcs_app'@'%' IDENTIFIED BY '$NEWPW'; FLUSH PRIVILEGES;"
    patch_secret lcs-db-users DB_RUNTIME_PASS "$NEWPW"
    patch_secret lcs-db-admin DB_APP_PASSWORD "$NEWPW"
    kubectl -n "$NS" rollout restart deploy/lcs-laravel-prod deploy/lcs-queue-prod deploy/lcs-scheduler-prod
    ;;
  lcs_migrate)
    kubectl -n "$NS" exec statefulset/mariadb -- mariadb -uroot -p"$ROOT" \
      -e "ALTER USER 'lcs_migrate'@'%' IDENTIFIED BY '$NEWPW'; FLUSH PRIVILEGES;"
    patch_secret lcs-db-admin DB_MIGRATE_PASSWORD "$NEWPW"
    patch_secret lcs-db-admin DB_RUNTIME_PASS "$NEWPW"
    echo "  (el migrate-job tomará la nueva en el próximo deploy)"
    ;;
  redis)
    kubectl -n "$NS" exec deploy/redis -- sh -c \
      "redis-cli -a \"\$REDIS_PASSWORD\" CONFIG SET requirepass '$NEWPW' && redis-cli -a '$NEWPW' CONFIG REWRITE" || true
    patch_secret lcs-db-users REDIS_PASSWORD "$NEWPW"
    kubectl -n "$NS" rollout restart deploy/redis deploy/lcs-laravel-prod deploy/lcs-queue-prod deploy/lcs-scheduler-prod
    ;;
  *)
    echo "target desconocido: $TARGET"; exit 1;;
esac

if [ -f "$SECRETS_MD" ]; then
  printf '| %s | %s | rotación %s |\n' "$(date +%F)" "$TARGET" "$NEWPW" >> "$SECRETS_MD"
  echo "clave nueva anexada a $SECRETS_MD"
fi
echo
echo ">>> $TARGET rotado. Clave nueva: $NEWPW"
echo ">>> Guardala también en tu gestor de contraseñas."
