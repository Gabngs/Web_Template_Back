# Runbook — Usuarios de BD, secrets y endurecimiento

> Las **contraseñas reales** no van acá: están en `.secrets/credenciales-bd.md`
> (gitignored). Este archivo es el *procedimiento*.

Contexto: 1 VPS, k3s, namespace `lcs-prod`. Bases sobre un MariaDB:
`db_lcs`, `db_siaw`, `db_sincro`.

---

## 0. Modelo de secrets (después de estos cambios)

| Secret | Contiene | Lo montan |
|---|---|---|
| `lcs-env` | todo lo de la app **menos** el root de BD | api, queue, scheduler, migrate-job |
| `lcs-db-root` | `DB_ROOT_PASSWORD` | **solo** el StatefulSet de MariaDB y el migrate-job |
| `lcs-db-users` | `DB_RUNTIME_USER=lcs_app`, `DB_RUNTIME_PASS=<app>`, `REDIS_PASSWORD` | api, queue, scheduler |
| `lcs-db-admin` | `DB_APP_USERNAME/PASSWORD`, `DB_MIGRATE_USERNAME/PASSWORD`, `DB_RUNTIME_USER=lcs_migrate`, `DB_RUNTIME_PASS=<migrate>` | **solo** el migrate-job |

`config/database.php` ahora lee `DB_RUNTIME_USER` / `DB_RUNTIME_PASS` para todas
las 3 conexiones (`dblcs`, `dbsiaw`, `dbsincro`), con **fallback** a los `DB_*_USERNAME/PASSWORD`
per-módulo → si no creás los secrets nuevos, **nada cambia**.

- pods de app → `lcs-db-users` les da `lcs_app` (solo DML).
- migrate-job → `lcs-db-admin` le da `lcs_migrate` (DML + DDL) para `artisan migrate`.

---

## 1. Alta inicial de los usuarios (una vez, desde tu máquina con kubeconfig de prod)

### 1.1 Crear los tres secrets

Sacá las claves de `.secrets/credenciales-bd.md`.

```bash
NS=lcs-prod

# root: reusar el que YA tiene el cluster (para no romper MariaDB)
ROOT=$(kubectl -n $NS get secret lcs-env -o jsonpath='{.data.DB_ROOT_PASSWORD}' | base64 -d)
kubectl -n $NS create secret generic lcs-db-root \
  --from-literal=DB_ROOT_PASSWORD="$ROOT"

# usuario de app (DML)
kubectl -n $NS create secret generic lcs-db-users \
  --from-literal=DB_RUNTIME_USER=lcs_app \
  --from-literal=DB_RUNTIME_PASS='<APP_PW de credenciales-bd.md>' \
  --from-literal=REDIS_PASSWORD='<REDIS_PW>'

# definiciones para ensure-databases.php + creds del job (DDL)
kubectl -n $NS create secret generic lcs-db-admin \
  --from-literal=DB_APP_USERNAME=lcs_app \
  --from-literal=DB_APP_PASSWORD='<APP_PW>' \
  --from-literal=DB_MIGRATE_USERNAME=lcs_migrate \
  --from-literal=DB_MIGRATE_PASSWORD='<MIGRATE_PW>' \
  --from-literal=DB_RUNTIME_USER=lcs_migrate \
  --from-literal=DB_RUNTIME_PASS='<MIGRATE_PW>'
```

### 1.2 Crear los usuarios en MariaDB

**Opción A — automática:** simplemente hacé un deploy (push a `main`). El
migrate-job corre `k8s/scripts/ensure-databases.php`, que ya
`CREATE USER IF NOT EXISTS` + `GRANT` para `lcs_app` y `lcs_migrate` leyendo
esos secrets. Idempotente.

**Opción B — a mano ahora (sin esperar deploy):**

```bash
kubectl -n lcs-prod exec -it statefulset/mariadb -- \
  mariadb -uroot -p"$ROOT" <<SQL
CREATE USER IF NOT EXISTS 'lcs_app'@'%'     IDENTIFIED BY '<APP_PW>';
CREATE USER IF NOT EXISTS 'lcs_migrate'@'%' IDENTIFIED BY '<MIGRATE_PW>';
$(for db in db_lcs db_siaw db_sincro; do
  echo "GRANT SELECT,INSERT,UPDATE,DELETE ON \`$db\`.* TO 'lcs_app'@'%';"
  echo "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,CREATE TEMPORARY TABLES,LOCK TABLES,EXECUTE,CREATE VIEW,SHOW VIEW,TRIGGER,REFERENCES ON \`$db\`.* TO 'lcs_migrate'@'%';"
done)
FLUSH PRIVILEGES;
SQL
```

### 1.3 Cortar los pods de app a `lcs_app`

Ya está en los manifiestos (`envFrom … lcs-db-users optional`). Redeployá:
`kubectl -n lcs-prod rollout restart deploy/lcs-laravel-prod deploy/lcs-queue-prod deploy/lcs-scheduler-prod`.
Verificá: `kubectl -n lcs-prod exec deploy/lcs-laravel-prod -- php artisan tinker --execute="DB::connection('dblcs')->select('select current_user()')"`.

### 1.4 Sacar `DB_ROOT_PASSWORD` de `lcs-env`

Una vez que `lcs-db-root` existe y MariaDB lo referencia:

```bash
kubectl -n lcs-prod get secret lcs-env -o json \
  | jq 'del(.data.DB_ROOT_PASSWORD)' \
  | kubectl apply -f -
kubectl -n lcs-prod rollout restart deploy/lcs-laravel-prod
```

Ahora el pod público **ya no tiene el root** en su entorno.

---

## 2. Ver / definir / cambiar contraseñas

### "¿Cómo la defino?"
Vos la generás y la ponés en el secret. MariaDB no genera nada.
```bash
openssl rand -base64 30 | tr -d '/+=' | cut -c1-32
```

### "¿Cómo la veo?" (la que ya está en el cluster)
```bash
kubectl -n lcs-prod get secret lcs-db-users  -o jsonpath='{.data.DB_RUNTIME_PASS}'   | base64 -d; echo
kubectl -n lcs-prod get secret lcs-db-admin  -o jsonpath='{.data.DB_MIGRATE_PASSWORD}'| base64 -d; echo
kubectl -n lcs-prod get secret lcs-db-root   -o jsonpath='{.data.DB_ROOT_PASSWORD}'   | base64 -d; echo
```

### "¿Cómo la reseteo?" (usuario de app / migración)
`ALTER USER` como root, y actualizás el secret. El script `rotate-db-passwords.sh`
lo hace de una (§3). A mano:
```bash
kubectl -n lcs-prod exec statefulset/mariadb -- \
  mariadb -uroot -p"$ROOT" -e "ALTER USER 'lcs_app'@'%' IDENTIFIED BY 'nueva'; FLUSH PRIVILEGES;"
kubectl -n lcs-prod patch secret lcs-db-users --type=merge \
  -p "{\"data\":{\"DB_RUNTIME_PASS\":\"$(printf 'nueva' | base64 -w0)\"}}"
kubectl -n lcs-prod rollout restart deploy/lcs-laravel-prod deploy/lcs-queue-prod deploy/lcs-scheduler-prod
```

### "¿Y si me olvido el root?"
- Si está en un secret: `kubectl get secret lcs-db-root -o jsonpath='{.data.DB_ROOT_PASSWORD}' | base64 -d`.
- Si el secret también se perdió: recovery con grant tables desactivadas.
  ```bash
  # editar el StatefulSet: command: ["mariadbd","--skip-grant-tables","--skip-networking=0"]
  kubectl -n lcs-prod exec -it statefulset/mariadb -- mariadb -uroot <<'SQL'
  FLUSH PRIVILEGES;
  ALTER USER 'root'@'localhost' IDENTIFIED BY 'nuevo-root';
  SQL
  # revertir el command del StatefulSet y recrear el secret lcs-db-root
  ```
- Rotar el root "normal" (con acceso): `ALTER USER 'root'@'localhost' IDENTIFIED BY '…'` + actualizar `lcs-db-root`. **Ojo:** solo cambiar el secret NO cambia el root real; hay que hacer el `ALTER`.

---

## 3. Rotación periódica

Script: `k8s/scripts/rotate-db-passwords.sh` (uso manual o desde un cron/routine).
Genera claves nuevas, hace `ALTER USER` como root, actualiza los secrets y
reinicia los pods. Uso:

```bash
./k8s/scripts/rotate-db-passwords.sh lcs_app         # rota solo la de app
./k8s/scripts/rotate-db-passwords.sh lcs_migrate      # rota la de migración
./k8s/scripts/rotate-db-passwords.sh redis            # rota redis
```

Cada corrida imprime la clave nueva y actualiza `.secrets/credenciales-bd.md`
(en tu máquina). Cadencia sugerida: **cada 90 días** app/migrate, **al salir
alguien del equipo** siempre.

Automatizable con `/schedule` (routine cloud) o un CronJob k8s que llame al
script — pero como toca secrets y reinicia pods, mejor dejarlo manual/semi.

---

## 4. "Me conecto al VPS con un archivo — ¿cualquiera que lo tenga entra?"

Sí. `~/.ssh/lcs_tunnel_ed25519` es una llave **sin passphrase**: quien tenga el
archivo entra. Opciones, de menos a más fricción:

### 4.1 Passphrase en la llave (mínimo indispensable)
```bash
ssh-keygen -p -f ~/.ssh/lcs_tunnel_ed25519      # te pide passphrase nueva
```
Ahora cada uso pide la frase. Para no tipearla en cada comando: `ssh-agent`
la cachea por sesión (`ssh-add ~/.ssh/lcs_tunnel_ed25519`, se te pide 1 vez).
Windows: `Start-Service ssh-agent; ssh-add`.

### 4.2 Que el VPS pida contraseña ante comandos con privilegio
En el VPS, el usuario NO debe tener `NOPASSWD` en sudoers:
```bash
sudo visudo         # que NO haya líneas "usuario ALL=(ALL) NOPASSWD:ALL"
```
Así, aunque entren por SSH, `sudo <algo>` pide la contraseña del usuario.

### 4.3 MFA en SSH (TOTP — Google Authenticator)
```bash
sudo apt install libpam-google-authenticator
google-authenticator            # como el usuario; escaneás el QR
# /etc/pam.d/sshd:  auth required pam_google_authenticator.so
# /etc/ssh/sshd_config:  KbdInteractiveAuthentication yes
#                        AuthenticationMethods publickey,keyboard-interactive
sudo systemctl restart ssh
```
Ahora entrar pide **llave + código TOTP** del celular.

### 4.4 kubeconfig
El kubeconfig es un token/cert — no tiene passphrase. Protegelo:
- `chmod 600 ~/.kube/config`, en disco cifrado (BitLocker/LUKS).
- Usá un context/usuario con permisos acotados si no necesitás cluster-admin.
- Rotable: si se filtra, en el VPS `k3s` regenerá el token del SA o rehacé el
  kubeconfig de admin (`/etc/rancher/k3s/k3s.yaml`) y revocá el viejo.
- El `KUBECONFIG_PROD` de GitHub Actions: rotarlo si sospechás filtración.

### 4.5 Endurecer sshd (recomendado)
`/etc/ssh/sshd_config`:
```
PasswordAuthentication no
PermitRootLogin no
AllowUsers <tu-usuario>
```
+ `fail2ban` para banear IPs con intentos fallidos.

---

## 5. Checklist de endurecimiento del VPS/cluster (pendiente, por prioridad)

- [ ] **Alta:** confirmar `APP_DEBUG=false` y `APP_ENV=production` en `lcs-env`
      (`kubectl -n lcs-prod get secret lcs-env -o jsonpath='{.data.APP_DEBUG}' | base64 -d`).
- [ ] **Alta:** completar §1 (usuarios acotados) y §1.4 (sacar root de `lcs-env`).
- [ ] **Media:** aplicar `k8s/06-network-policies.yaml` (primero los allow, después el default-deny).
- [ ] **Media:** poblar `REDIS_PASSWORD` en `lcs-db-users` → redis pasa a exigir auth.
- [ ] **Media:** `securityContext` en los 4 workloads:
      ```yaml
      securityContext:
        allowPrivilegeEscalation: false
        capabilities: { drop: ["ALL"], add: ["NET_BIND_SERVICE","CHOWN","SETUID","SETGID"] }
        seccompProfile: { type: RuntimeDefault }
      ```
      (probalo en staging: nginx bindea :80 y php-fpm hace setuid del worker).
- [ ] **Media:** disk encryption del VPS (los `Secret` de k8s están ~en claro en etcd).
- [ ] **Baja:** middleware de redirect http→https y rateLimit en el Ingress de Traefik.
- [ ] **Baja:** `PodDisruptionBudget` + límites al migrate-job (ya agregados).
- [ ] **Baja:** quitar `auth:rotate-keys` del migrate-job o del scheduler (está en los dos).

### ¿Es vulnerable a "ataque simple desde internet"?
La capa app está razonablemente dura: rate-limit (login 5/min, API 120/tok · 30/ip),
lockout por cuenta, headers de seguridad, TLS, auth bearer, login con desafío RSA,
Swagger gateado. Fuerza bruta / credential-stuffing lento / clickjacking / sniffing
están cubiertos. El riesgo real **no** es "poco esfuerzo desde afuera", es la
**amplificación post-compromiso**: hoy, una sola RCE/SSRF en la app o en una
dependencia escala a root de MariaDB + Redis + todas las bases sin fricción. Los
tres items "Alta/Media" de arriba (usuarios acotados, aislar el root,
NetworkPolicy) son los que cierran ese hueco.
