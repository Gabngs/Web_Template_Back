#!/bin/sh
# Entrypoint de la imagen runtime.
#
# Calienta las caches de Laravel ANTES de arrancar php-fpm/nginx, así el primer
# request (y cada worker fpm) no parsea ~25 archivos de config ni recompone el
# árbol de eventos. Best-effort: si algo falla, se limpia la cache para que la
# app arranque sin cachear en vez de romperse.
#
# Nota: los Deployments de queue/scheduler y el Job de migración sobrescriben
# `command:` en K8s, así que NO pasan por acá — solo el Deployment de API lo usa.
# El Job calienta config:cache por su cuenta en 05-job-migrate.yaml.
set -e

php artisan config:cache 2>/dev/null || php artisan config:clear || true
php artisan event:cache  2>/dev/null || true
# route:cache queda pendiente hasta eliminar las 2 closures de acción en
# routes/api/api.php y routes/web.php (ver docs/RUNBOOK_BD_Y_SEGURIDAD.md).

# Encadena al entrypoint original de la imagen php:8.4-fpm (docker-php-entrypoint)
# para no perder su comportamiento base.
if [ -x /usr/local/bin/docker-php-entrypoint ]; then
    exec docker-php-entrypoint "$@"
fi
exec "$@"
