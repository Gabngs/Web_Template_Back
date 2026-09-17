# Configurar secrets al clonar este repo

Este repo es la base **LCS (Laravel Core Standard)**: Laravel + SIAW (usuarios/roles/permisos/menús) + Auth (RSA + Sanctum) + despliegue K3s, listo para partir un proyecto nuevo. Checklist de lo que hay que configurar la primera vez que se clona — ya sea para levantarlo en local o para desplegarlo a un VPS nuevo.

Ver también, en el Vault (`Template Estandar Backend/Despliegue/`): `Checklist Nuevo Proyecto.md`, `Manifiestos Kubernetes.md`, `CI-CD GitHub Actions.md`.

---

## 0. (Opcional) Renombrar el prefijo `lcs` a tu proyecto

Este repo trae el prefijo `lcs` / conexión `dblcs` como base neutra. Si vas a arrancar un producto real con nombre propio (ej. `crm_`, `mdt_`), renombrar antes de tocar nada más:

| Dónde | Qué cambiar |
|---|---|
| `config/database.php` | conexión `dblcs` → `db{prefijo}`, envs `DB_LCS_*` → `DB_{PREFIJO}_*` |
| `.env` / `.env.example` | mismas variables `DB_LCS_*` |
| `docker-compose.yml`, `setup.sh`, `start.sh` | nombres de servicio `lcs_*` → `{prefijo}_*` |
| `k8s/*.yaml`, `.github/workflows/deploy.yml` | `lcs-prod`, `lcs-laravel-prod`, `lcs-env`, `ghcr.io/.../lcs`, etc. |
| `database/docker-init/01_init_databases.sql`, `k8s/01-mariadb.yaml` | `db_lcs` → `db_{prefijo}` |
| `config/sistemas.php`, `SiawSistemasSeeder` | código de sistema `'LCS'` → el que corresponda |

`dbsiaw` y `dbsincro` **no cambian** — son las dos conexiones fijas del estándar, compartidas por convención en todo proyecto que lo use.

---

## 1. Local (Docker Compose / Sail)

```bash
bash setup.sh   # genera .env interactivo (o copiá .env.example a mano) — se autoelimina
bash start.sh   # build + up + migrate + seed + claves RSA + swagger
```

`start.sh` genera las claves RSA solas (`php artisan auth:rotate-keys`) — no hace falta crear nada a mano. Nada de Discord ni de mail hace falta en local: `MAIL_MAILER=smtp` apunta a `lcs_mailpit` (http://localhost:8026) y `DISCORD_WEBHOOK_URL` puede quedar vacío.

---

## 2. GitHub Actions (CI/CD)

Settings → Secrets and variables → Actions, en el repo:

| Secret | Contenido | Obligatorio |
|---|---|---|
| `KUBECONFIG_PROD` | kubeconfig del cluster K3s de destino (base64 o texto plano, según cómo lo lea tu `reusable-deployment.yml`) | Sí, para que el job `deploy` corra |
| `DISCORD_WEBHOOK_URL` | Webhook del canal de notificaciones de deploy | No — sin él, los pasos de notificación se saltan solos (`set +e` + `exit 0` si está vacío) |
| `DISCORD_USERNAME` | Nombre del bot para diferenciar este proyecto en el canal | No |

`GITHUB_TOKEN` para el push a `ghcr.io` es automático — no hace falta crearlo. El repo necesita `packages: write` en el workflow (ya está en `.github/workflows/deploy.yml`).

Cómo crear un webhook de Discord: Configuración del canal → Integraciones → Webhooks → Nuevo Webhook → copiar URL.

---

## 3. Cluster K3s — secrets de la app

Una sola vez, antes del primer deploy (ver `Checklist Nuevo Proyecto.md` en el Vault para el detalle completo con namespace/`ghcr-secret`):

```bash
NS=lcs-prod
kubectl create namespace $NS

kubectl -n $NS create secret docker-registry ghcr-secret \
  --docker-server=ghcr.io \
  --docker-username={usuario-github} \
  --docker-password={PAT_read_packages} \
  --docker-email=noreply@tudominio.com

kubectl -n $NS create secret generic lcs-env \
  '--from-literal=APP_KEY=base64:...' \
  '--from-literal=DB_CONNECTION=dbsiaw' \
  '--from-literal=DB_LCS_HOST=mariadb-service' \
  '--from-literal=DB_LCS_USERNAME=...' \
  '--from-literal=DB_LCS_PASSWORD=...' \
  '--from-literal=DB_LCS_DATABASE=db_lcs' \
  '--from-literal=DB_SIAW_HOST=mariadb-service' \
  '--from-literal=DB_SIAW_USERNAME=...' \
  '--from-literal=DB_SIAW_PASSWORD=...' \
  '--from-literal=DB_SIAW_DATABASE=db_siaw' \
  '--from-literal=DB_SINCRO_HOST=mariadb-service' \
  '--from-literal=DB_SINCRO_USERNAME=...' \
  '--from-literal=DB_SINCRO_PASSWORD=...' \
  '--from-literal=DB_SINCRO_DATABASE=db_sincro' \
  '--from-literal=REDIS_HOST=redis-service' \
  '--from-literal=REDIS_PORT=6379' \
  '--from-literal=REDIS_CLIENT=phpredis' \
  '--from-literal=CACHE_STORE=redis' \
  '--from-literal=DB_CACHE_CONNECTION=dbsincro' \
  '--from-literal=QUEUE_CONNECTION=database' \
  '--from-literal=DB_QUEUE_CONNECTION=dbsincro' \
  '--from-literal=SESSION_DRIVER=database' \
  '--from-literal=SESSION_CONNECTION=dbsincro' \
  '--from-literal=SESSION_LIFETIME=120' \
  '--from-literal=LOG_CHANNEL=stack' \
  '--from-literal=LOG_STACK=daily' \
  '--from-literal=LOG_DAILY_DAYS=14' \
  '--from-literal=CORS_ALLOWED_ORIGINS=https://tu-frontend.com' \
  '--from-literal=MAIL_MAILER=resend' \
  '--from-literal=RESEND_API_KEY=re_xxx' \
  '--from-literal=MAIL_FROM_ADDRESS=no-reply@tudominio.com'
```

Usar comillas simples en cada `--from-literal='KEY=VALUE'` — sin esto, bash expande `$` dentro de las passwords antes de que `kubectl` las reciba.

### Secrets acotados (usuarios de BD con permisos mínimos, opcional pero recomendado)

Ver `docs/RUNBOOK_BD_Y_SEGURIDAD.md` — separa el root (`lcs-db-root`), el usuario de runtime de la app (`lcs-db-users` → `lcs_app`, solo DML) y el usuario del Job de migración (`lcs-db-admin` → `lcs_migrate`, DML+DDL). Mientras no se creen estos tres, todo cae al fallback de `DB_*_USERNAME/PASSWORD` de `lcs-env` — el cluster funciona igual, solo que sin la separación de privilegios.

---

## 4. Claves RSA (login)

**No se generan a mano ni se suben como secret.** Viven en el PVC `lcs-rsa-keys-pvc` (`k8s/00-rsa-keys-pvc.yaml`), y se generan/rotan solas con `php artisan auth:rotate-keys`:
- El Job de migración lo corre una vez por deploy.
- El pod `scheduler` lo corre al arrancar y cada rotación programada (slot de 2h).

No hace falta ningún paso manual acá — aplicar `k8s/00-rsa-keys-pvc.yaml` antes que el resto alcanza.

---

## 5. Correo (Resend)

Local: `lcs_mailpit` captura los correos sin salir a internet (http://localhost:8026), no requiere configuración.

Producción: el paquete `resend/resend-php` ya está instalado — solo falta:
1. Verificar el dominio propio en Resend (registros DNS).
2. Setear en `lcs-env`: `MAIL_MAILER=resend`, `RESEND_API_KEY=re_xxx`, `MAIL_FROM_ADDRESS=no-reply@tudominio.com`.

---

## 6. Reactivar el deploy automático

`.github/workflows/deploy.yml` arranca con `on: workflow_dispatch` (disparo manual desde la pestaña Actions de GitHub) **a propósito** — así el repo no publica ni intenta desplegar nada solo con un push mientras no haya secrets configurados. Una vez que `KUBECONFIG_PROD` y el resto de este checklist estén listos, cambiar el trigger a:

```yaml
on:
  push:
    branches: [main]
```

Recién ahí cada push a `main` dispara build + deploy automático.

---

## 7. Resumen — qué es obligatorio para el primer deploy

| Sin esto... | ...pasa |
|---|---|
| `KUBECONFIG_PROD` | El job `deploy` de GitHub Actions falla al autenticar contra el cluster |
| `ghcr-secret` | Los pods no pueden bajar la imagen (`ImagePullBackOff`) |
| `lcs-env` con `APP_KEY`/`DB_*` | La app arranca en 500 (sin `APP_KEY`) o no conecta a BD |
| `lcs-rsa-keys-pvc` aplicado | El login RSA falla — no hay dónde persistir las claves |
| `DISCORD_WEBHOOK_URL` | El deploy sigue funcionando, simplemente no notifica |
| `RESEND_API_KEY` | Los correos de password no salen (quedan en el log si `MAIL_MAILER=log`) |
