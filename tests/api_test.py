#!/usr/bin/env python3
"""
Test de requests contra la API local (Docker) — dinámico, guiado por Swagger.

Qué hace:
  1. Verifica que Docker Desktop y los contenedores del proyecto estén arriba;
     si no, los levanta (Docker Desktop + ./start.sh).
  2. Regenera storage/api-docs/api-docs.json (l5-swagger) para detectar modelos nuevos.
  3. Login con el usuario del seeder (/auth/login-dev, solo local).
  4. Por cada modelo (recurso con GET/POST en /api/{modelo}) prueba:
       listar (paginate/tiny/per_page) · crear (201) · validación (422) · mostrar ·
       filtros · búsqueda · orden · editar · 404 · eliminar · mostrar-tras-eliminar.
     Escribe de verdad en la BD y limpia lo que crea (soft delete según el modelo).

Un modelo nuevo se prueba solo en cuanto tenga sus anotaciones @OA (Swagger).

Uso (desde la raíz del proyecto, sin dependencias extra — solo stdlib):
  python tests/api_test.py [opciones]

Qué modelos probar:
  (sin opciones)                  todos los modelos detectados en Swagger
  --model, -m a,b                 solo esos modelos, separados por coma
  --<modelo>                      atajo: uno o varios modelos sueltos (ej. --lcs_catalogo --siaw_roles)
  --list                          lista los modelos detectados y termina

Autenticación:
  --user, --email EMAIL_O_CODIGO  usuario (email o código de 5 dígitos). Default: env API_TEST_USER o SIAW_ADMIN_EMAIL_INICIAL
  --password X                    contraseña. Default: env API_TEST_PASSWORD o SIAW_ADMIN_PASSWORD_INICIAL
  --ask-password, -p              pide la contraseña por teclado (no queda en el historial)
  --token "KEY@1|abc..."          usa un Bearer token ya obtenido (omite el login)

Qué pasa con los datos que crea la prueba (mutuamente excluyentes):
  (sin opciones)                  elimina lo que crea (soft delete según el modelo)
  --perm                          conserva los registros que crea (no hace DELETE ni limpia padres)
  --undo                          al terminar borra de la BD todo lo que creó la corrida, incl. soft-deleted
  --clear                         al terminar hace TRUNCATE de la tabla de cada modelo probado: vacía TODA la
                                  tabla (no solo lo creado por la corrida) y reinicia pkid. Solo en BD local
  (--undo y --clear requieren acceso a la BD por Docker; --perm no puede combinarse con ninguno de los dos)

Salida y entorno:
  --txt [RUTA]                    además guarda los resultados (default: tests/results/api_test_<fecha>.txt)
  -v, --verbose                   salida detallada
  --base-url URL                  default: http://localhost:APP_PORT del .env
  --root RUTA                     raíz del proyecto Laravel (default: se detecta desde el cwd / el script)
  --app-container NOMBRE          contenedor de la app (para Swagger). Default: el que publica el puerto de la URL base
  --db-container NOMBRE           fuerza un único contenedor de BD (default: default: el que publica FORWARD_DB_PORT del .env)
  --fk CAMPO_ID=RECURSO           fuerza una relación que no se detecte (repetible). Normalmente innecesario
  --no-docker                     no verifica ni levanta Docker
  --no-refresh                    no regenera Swagger
  --no-db                         no verifica los datos directamente en la BD

Ejemplos:
  python tests/api_test.py --model lcs_catalogo,lcs_catalogo_det --txt --user admin@example.com --password X
  python tests/api_test.py --model lcs_catalogo --clear     # prueba y deja la tabla vacía
  python tests/api_test.py --user otro@mail.com -p
"""
import argparse
import json
import os
import random
import re
import shutil
import string
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from datetime import datetime, timedelta
from pathlib import Path

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

def find_root(explicit=None):
    """Raíz del proyecto Laravel: --root, o el primer directorio (desde el cwd y desde el script, hacia arriba)
    que tenga `artisan`; si no, el primero con `.env`. Así funciona desde cualquier carpeta (tests/, tests/manual/...)."""
    if explicit:
        return Path(explicit).resolve()
    starts = [Path.cwd().resolve(), Path(__file__).resolve().parent]
    for marker in ("artisan", ".env"):
        for st in starts:
            for d in [st, *st.parents]:
                if (d / marker).exists():
                    return d
    return Path.cwd().resolve()


ROOT = find_root()
# Se resuelven en main(): --app-container/--db-container, env API_TEST_APP_CONTAINER, o el contenedor que publica
# el puerto del .env (APP_PORT / FORWARD_DB_PORT). Nada fijo.
APP_CONTAINER = os.environ.get("API_TEST_APP_CONTAINER")
DB_CONTAINER = None
DOCKER_DESKTOP = r"C:\Program Files\Docker\Docker\Docker Desktop.exe"
# Rutas que no son CRUD de modelo: se excluyen del descubrimiento.
SKIP_RESOURCES = {"auth"}
# Respaldo manual "campo_id" -> recurso, solo si no se pueden leer las relaciones de los modelos
# (sin Docker) y la heurística por nombre no acierta. Normalmente vacío: se usa --fk campo_id=recurso.
FK_MAP = {}

# Se ejecuta dentro del contenedor de la app: lee los belongsTo de los modelos Eloquent
# -> {tabla: {columna_fk: tabla_relacionada}}
PHP_RELATIONS = r"""<?php
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out = [];
$has = [];
foreach (glob(getcwd() . '/app/Models/{,*/}*.php', GLOB_BRACE) as $f) {
    $rel = str_replace([getcwd() . '/app/', '/', '.php'], ['App\\', '\\', ''], $f);
    if (!class_exists($rel)) continue;
    $rc = new ReflectionClass($rel);
    if ($rc->isAbstract() || !$rc->isSubclassOf(Illuminate\Database\Eloquent\Model::class)) continue;
    $model = new $rel;
    foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
        if ($m->class !== $rel || $m->getNumberOfParameters() > 0 || !$m->getReturnType()) continue;
        $rt = (string) $m->getReturnType();
        try {
            if (is_a($rt, Illuminate\Database\Eloquent\Relations\BelongsTo::class, true)) {
                $r = $m->invoke($model);
                $out[$model->getTable()][$r->getForeignKeyName()] = $r->getRelated()->getTable();
            } elseif (is_a($rt, Illuminate\Database\Eloquent\Relations\HasOneOrMany::class, true)) {
                // hasMany/hasOne: la FK vive en la tabla hija y apunta a este modelo
                $r = $m->invoke($model);
                $has[] = [$r->getRelated()->getTable(), $r->getForeignKeyName(), $model->getTable()];
            }
        } catch (Throwable $e) {}
    }
}
// El belongsTo del hijo manda; el hasMany del padre solo completa lo que el hijo no declara.
foreach ($has as [$child, $fk, $parent]) {
    if (!isset($out[$child][$fk])) $out[$child][$fk] = $parent;
}
echo '@@' . json_encode($out) . '@@';
"""


def detect_container(host_port):
    """Nombre del contenedor en marcha que publica `host_port` en el host."""
    if not host_port:
        return None
    p = run(["docker", "ps", "--format", "{{.Names}}	{{.Ports}}"])
    for line in p.stdout.splitlines():
        name, _, ports = line.partition("	")
        if re.search(rf":{host_port}->\d+/tcp", ports):
            return name
    return None


def load_relations():
    """Relaciones (belongsTo + hasMany/hasOne) leídas de los modelos del proyecto. {} si no se pueden leer."""
    if not APP_CONTAINER:
        return {}
    p = subprocess.run(["docker", "exec", "-i", APP_CONTAINER, "php"], input=PHP_RELATIONS, capture_output=True,
                       text=True, encoding="utf-8", errors="ignore")
    m = re.search(r"@@(.*)@@", p.stdout, re.S)
    try:
        return json.loads(m.group(1)) if m else {}
    except ValueError:
        return {}
# Query params estándar del toolkit: no son "filtros" de columna.
STD_PARAMS = {"paginate", "page", "per_page", "tiny", "search", "sorts", "include", "id"}

C = {"g": "\033[92m", "r": "\033[91m", "y": "\033[93m", "b": "\033[94m", "d": "\033[90m", "0": "\033[0m"}
if not sys.stdout.isatty() or os.environ.get("NO_COLOR"):
    C = {k: "" for k in C}


# ── Utilidades ───────────────────────────────────────────────────────────────
def read_env(key, default=None):
    f = ROOT / ".env"
    if f.exists():
        for line in f.read_text(encoding="utf-8", errors="ignore").splitlines():
            m = re.match(rf"^{key}=(.*)$", line.strip())
            if m:
                return m.group(1).strip().strip('"').strip("'")
    return default


def run(cmd, **kw):
    return subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="ignore", **kw)


class Report:
    def __init__(self):
        self.lines, self.ok, self.fail, self.skip = [], 0, 0, 0
        self.failures = []

    def out(self, text="", color=""):
        print(f"{C.get(color, '')}{text}{C['0']}")
        self.lines.append(re.sub(r"\033\[[0-9;]*m", "", text))

    def check(self, model, name, cond, detail=""):
        if cond:
            self.ok += 1
            self.out(f"  [OK]   {name}", "g")
        else:
            self.fail += 1
            self.failures.append(f"{model}: {name} — {detail}")
            self.out(f"  [FAIL] {name}  {detail}", "r")
        return cond

    def skipped(self, name, why):
        self.skip += 1
        self.out(f"  [SKIP] {name}  ({why})", "y")


R = Report()
VERBOSE = False
PERM = False  # --perm: no eliminar (ni soft delete) los registros que crea la corrida


# ── Docker ───────────────────────────────────────────────────────────────────
def docker_ready():
    return shutil.which("docker") is not None and run(["docker", "info"]).returncode == 0


def resolve_containers(base, app_arg=None, db_arg=None):
    """Contenedor de la app y de la BD: argumento > env > el que publica el puerto del .env."""
    global APP_CONTAINER, DB_CONTAINER
    APP_CONTAINER = app_arg or APP_CONTAINER or detect_container(urllib.parse.urlparse(base).port)
    DB_CONTAINER = db_arg or next((x["container"] for x in discover_db_servers()), None)


def ensure_docker(base, app_arg=None, db_arg=None):
    if not docker_ready():
        if shutil.which("docker") is None:
            sys.exit("Docker no está instalado / no está en el PATH.")
        R.out(">>> Docker no responde — iniciando Docker Desktop...", "y")
        if os.path.exists(DOCKER_DESKTOP):
            subprocess.Popen([DOCKER_DESKTOP], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(60):  # hasta ~5 min
            if docker_ready():
                break
            time.sleep(5)
        else:
            sys.exit("Docker Desktop no arrancó a tiempo.")
    R.out(">>> Docker listo", "g")

    resolve_containers(base, app_arg, db_arg)
    needed = [n for n in (APP_CONTAINER, DB_CONTAINER) if n]
    running = lambda n: run(["docker", "inspect", "-f", "{{.State.Running}}", n]).stdout.strip() == "true"
    if not needed or not all(running(n) for n in needed):
        R.out(">>> Contenedores del proyecto caídos — ejecutando ./start.sh ...", "y")
        bash = shutil.which("bash")
        if not (ROOT / "start.sh").exists():
            sys.exit(f"No encuentro el contenedor de la app (puerto {urllib.parse.urlparse(base).port}) o de la BD, y no hay start.sh en {ROOT}. Levántalos a mano o usa --no-docker.")
        if not bash:
            sys.exit("No encuentro bash (Git Bash) para ejecutar start.sh. Levanta el proyecto manualmente.")
        p = subprocess.run([bash, "start.sh"], cwd=ROOT)
        resolve_containers(base, app_arg, db_arg)
        needed = [n for n in (APP_CONTAINER, DB_CONTAINER) if n]
        if p.returncode != 0:
            R.out(">>> start.sh terminó con error — sigo si la API responde (en el 1er arranque la BD puede tardar).", "y")
    R.out(f">>> Contenedor app: {APP_CONTAINER or 'no detectado'} · BD: {DB_CONTAINER or 'no detectado'}", "g")

    for _ in range(40):
        try:
            urllib.request.urlopen(base + "/api/", timeout=5)
            break
        except urllib.error.HTTPError:
            break  # respondió algo (aunque sea 4xx/5xx) -> el server está arriba
        except BaseException as e:  # incluye resets de conexión / respuestas raras de otro servicio
            if isinstance(e, KeyboardInterrupt):
                raise
            time.sleep(3)
    else:
        sys.exit(f"La API no responde en {base}")


def refresh_swagger():
    if not APP_CONTAINER:
        return R.out("    sin contenedor de la app: no regenero Swagger, uso el JSON existente", "y")
    R.out(">>> Regenerando Swagger (l5-swagger:generate)...", "d")
    p = run(["docker", "exec", APP_CONTAINER, "php", "artisan", "l5-swagger:generate"])
    if p.returncode != 0:
        R.out(f"    no se pudo regenerar ({p.stderr.strip()[:200]}), uso el JSON existente", "y")


# ── Base de datos (verificación directa en el contenedor) ───────────────────
def discover_db_servers(db_arg=None):
    """Servidores de BD del .env: cada DB_PORT[_X] con su DB_USERNAME[_X] / DB_PASSWORD[_X] / DB_DATABASE[_X].
    El contenedor es el que publica ese puerto en el host. Sin nada fijo: lo que cambie en el .env se refleja aquí."""
    servers, seen = [], set()
    env = ROOT / ".env"
    suffixes = []
    if env.exists():
        for line in env.read_text(encoding="utf-8", errors="ignore").splitlines():
            m = re.match(r"^DB_PORT(_[A-Za-z0-9]+)?\s*=", line.strip())
            if m:
                suffixes.append(m.group(1) or "")
    for suf in suffixes:
        container = db_arg or detect_container(read_env("DB_PORT" + suf))
        user, pwd = read_env("DB_USERNAME" + suf) or read_env("DB_USERNAME"), read_env("DB_PASSWORD" + suf)
        if pwd is None:
            pwd = read_env("DB_PASSWORD", "")
        database = read_env("DB_DATABASE" + suf)
        if not container or not user or (container, user, pwd, database) in seen:
            continue
        seen.add((container, user, pwd, database))
        servers.append({"container": container, "user": user, "pwd": pwd, "database": database, "client": None})
    # Convención del estándar LCS: un solo MariaDB para db_{prefijo}, db_siaw y db_sincro, publicado en
    # FORWARD_DB_PORT; el usuario DB_USERNAME tiene permisos sobre las tres. Cada tabla se ubica por
    # information_schema (sin fijar schema).
    if not servers:
        container = db_arg or detect_container(read_env("FORWARD_DB_PORT"))
        user, pwd = read_env("DB_USERNAME"), read_env("DB_PASSWORD", "")
        if container and user:
            servers.append({"container": container, "user": user, "pwd": pwd, "database": None, "client": None})
    return servers


class Db:
    """Consulta las BD con el cliente mariadb/mysql de cada contenedor de BD (docker exec).
    Hay un servidor por cada DB_PORT[_X] del .env; cada tabla se busca en su servidor (y en su DB_DATABASE[_X])."""

    def __init__(self):
        self.servers, self.enabled = [], False
        self.schemas = {}  # tabla -> (servidor, schema) | None

    def _exec(self, sql, srv):
        for client in ([srv["client"]] if srv["client"] else ["mariadb", "mysql"]):
            p = run(["docker", "exec", "-e", f"MYSQL_PWD={srv['pwd']}", srv["container"], client,
                     "-u", srv["user"], "-B", "--default-character-set=utf8mb4", "-e", sql])
            if p.returncode == 0:
                srv["client"] = client
                return p.stdout
        return None

    def connect(self, servers):
        self.servers = [s_ for s_ in servers if self._exec("SELECT 1", s_) is not None]
        self.enabled = bool(self.servers)
        return self.enabled

    def _locate(self, table):
        if table not in self.schemas:
            assert re.fullmatch(r"[A-Za-z0-9_]+", table)
            found = None
            for srv in self.servers:
                where = f"table_name='{table}'" + (f" AND table_schema='{srv['database']}'" if srv["database"] else "")
                out = self._exec(f"SELECT table_schema FROM information_schema.tables WHERE {where}", srv)
                lines = (out or "").strip().splitlines()[1:]
                if lines:
                    found = (srv, lines[0])
                    break
            self.schemas[table] = found
        return self.schemas[table]

    def schema_of(self, table):
        loc = self._locate(table)
        return loc[1] if loc else None

    def scalar(self, sql, table):
        """Ejecuta `sql` en el servidor donde vive `table`."""
        loc = self._locate(table)
        out = self._exec(sql, loc[0]) if loc else None
        lines = (out or "").strip().splitlines()
        return lines[1] if len(lines) > 1 else None

    def existing_id(self, table):
        """`id` (uuid, tal como está guardado) de una fila viva de `table`; None si no hay o no se puede consultar."""
        loc = self._locate(table)
        if not loc:
            return None
        cols = self._exec(f"SELECT column_name FROM information_schema.columns "
                          f"WHERE table_schema='{loc[1]}' AND table_name='{table}'", loc[0])
        names = set((cols or "").split()[1:])
        if "id" not in names:
            return None
        alive = [f"`{c}` IS NULL" for c in ("deleted_at", "deleted") if c in names]
        if "activo" in names:
            alive.append("`activo` = 1")
        where = " AND ".join(alive) or "1=1"
        # fila viva y activa AL AZAR (no siempre la primera) para que cada corrida use datos distintos
        return self.scalar(f"SELECT id FROM `{loc[1]}`.`{table}` WHERE id IS NOT NULL AND {where} ORDER BY RAND() LIMIT 1", table)

    def has_id(self, table, rid):
        """True/False si `rid` (tal cual) existe en table.id; None si no se pudo consultar."""
        schema = self.schema_of(table)
        if not schema or not re.fullmatch(r"[0-9a-fA-F-]{32,36}", str(rid)):
            return None
        n = self.scalar(f"SELECT COUNT(*) FROM `{schema}`.`{table}` WHERE id='{rid}'", table)
        return int(n) > 0 if n and n.isdigit() else None

    def purge(self, table, ids):
        """DELETE físico por uuid. Devuelve filas borradas (o None si falló)."""
        schema = self.schema_of(table)
        ids = [i for i in ids if re.fullmatch(r"[0-9a-fA-F]{8}(-?[0-9a-fA-F]{4}){3}-?[0-9a-fA-F]{12}", str(i))]
        if not schema or not ids:
            return None
        lst = ",".join(f"'{i}'" for i in ids)
        n = self.scalar(f"DELETE FROM `{schema}`.`{table}` WHERE id IN ({lst}); SELECT ROW_COUNT()", table)
        return int(n) if n and n.isdigit() else None

    def truncate(self, table):
        """TRUNCATE de toda la tabla (FK checks desactivados en la misma sesión). Devuelve las filas que había, o None si falló."""
        schema = self.schema_of(table)
        if not schema:
            return None
        before = self.scalar(f"SELECT COUNT(*) FROM `{schema}`.`{table}`", table)
        out = self._exec(f"SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE `{schema}`.`{table}`; SET FOREIGN_KEY_CHECKS=1", self._locate(table)[0])
        return int(before) if out is not None and before and before.isdigit() else None

    def row(self, table, rid):
        """dict con la fila (incluye deleted_at si existe), None si no hay fila, False si no se pudo consultar."""
        schema = self.schema_of(table)
        if not schema or not re.fullmatch(r"[0-9a-fA-F]{8}(-?[0-9a-fA-F]{4}){3}-?[0-9a-fA-F]{12}", str(rid)):
            return False
        out = self._exec(f"SELECT * FROM `{schema}`.`{table}` WHERE id='{rid}'", self._locate(table)[0])
        if out is None:
            return False
        lines = out.rstrip("\n").splitlines()
        if len(lines) < 2:
            return None
        return dict(zip(lines[0].split("\t"), lines[1].split("\t")))


DB = Db()


def show(label, data, limit=1500):
    """Imprime (terminal + txt) un dato del test: payload enviado, respuesta, fila en BD..."""
    txt = data if isinstance(data, str) else json.dumps(data, ensure_ascii=False, default=str)
    R.out(f"         ↳ {label}: {txt if len(txt) <= limit else txt[:limit] + '…'}", "d")


def match_row(sent, row):
    """Campos enviados que no coinciden con la fila de BD. Se omiten FKs (la BD guarda pkid, no uuid) y password."""
    bad = []
    for k, v in sent.items():
        if k not in row or k.endswith("_id") or k == "password" or isinstance(v, (dict, list)) or v is None:
            continue
        got = row[k]
        try:
            if isinstance(v, bool):
                ok = str(int(v)) == got
            elif isinstance(v, (int, float)):
                ok = float(v) == float(got)
            else:
                ok = str(v) == got
        except ValueError:
            ok = False
        if not ok:
            bad.append(f"{k}: enviado={v!r} BD={got!r}")
    return bad


def db_row_view(row):
    return {k: v for k, v in row.items() if v != "NULL"}


# ── HTTP ─────────────────────────────────────────────────────────────────────
CREATED = []  # (recurso, id) de todo lo creado vía POST durante la corrida (tests + padres de FK), en orden


class Api:
    def __init__(self, base):
        self.base, self.token = base, None

    def call(self, method, path, body=None, query=None, auth=True):
        url = self.base + path
        if query:
            url += "?" + urllib.parse.urlencode({k: int(v) if isinstance(v, bool) else v for k, v in query.items()})
        data = json.dumps(body).encode() if body is not None else None
        req = urllib.request.Request(url, data=data, method=method)
        req.add_header("Accept", "application/json")
        if data is not None:
            req.add_header("Content-Type", "application/json")
        if auth and self.token:
            req.add_header("Authorization", f"Bearer {self.token}")
        for attempt in range(4):
            try:
                with urllib.request.urlopen(req, timeout=60) as r:
                    code, raw = r.status, r.read()
            except urllib.error.HTTPError as e:
                code, raw = e.code, e.read()
            except Exception as e:
                return 0, {"_error": str(e)}
            if code != 429:
                break
            time.sleep(15)  # throttle:api (120/min) — esperar y reintentar
        try:
            js = json.loads(raw) if raw else {}
        except ValueError:
            js = {"_raw": raw[:300].decode("utf-8", "ignore")}
        if VERBOSE:
            R.out(f"      {method} {path} {query or ''} -> {code}", "d")
        if method == "POST" and code in (200, 201) and isinstance(js.get("data"), dict) and js["data"].get("id"):
            m = re.fullmatch(r"/api/([a-z0-9_]+)", path)
            if m and m.group(1) not in SKIP_RESOURCES:
                CREATED.append((m.group(1), js["data"]["id"]))
        return code, js


def jwt_claims(token):
    """Payload del JWT sin verificar firma (igual que el middleware ApiToken del proyecto)."""
    import base64
    try:
        part = token.split(".")[1]
        return json.loads(base64.urlsafe_b64decode(part + "=" * (-len(part) % 4)))
    except Exception:
        return {}


def keycloak_cfg():
    """Config de Keycloak del .env (KEYCLOAK_LINK/REALM/CLIENT_ID/SECRET), o None si el proyecto no la tiene."""
    link, realm, cid = read_env("KEYCLOAK_LINK"), read_env("KEYCLOAK_REALM"), read_env("KEYCLOAK_CLIENT_ID")
    if not (link and realm and cid):
        return None
    return {"url": f"{link.rstrip('/')}/{realm}/protocol/openid-connect/token", "client_id": cid,
            "secret": read_env("KEYCLOAK_SECRET")}


def login_keycloak(api, cfg, username, password):
    form = {"grant_type": "password", "client_id": cfg["client_id"], "username": username, "password": password}
    if cfg["secret"]:
        form["client_secret"] = cfg["secret"]
    req = urllib.request.Request(cfg["url"], data=urllib.parse.urlencode(form).encode(), method="POST")
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            js = json.loads(r.read())
    except urllib.error.HTTPError as e:
        sys.exit(f"Login Keycloak falló ({e.code}): {e.read()[:200].decode('utf-8', 'ignore')}")
    except Exception as e:
        sys.exit(f"No pude contactar a Keycloak ({cfg['url']}): {e}")
    api.token = js.get("access_token")
    return {"id": None, "email": username}


def login(api, email, password):
    code, js = api.call("POST", "/api/auth/login-dev", {"email": email, "password": password}, auth=False)
    if code != 200:
        sys.exit(f"Login falló ({code}): {js}. ¿Corrieron migraciones/seeders? Revisa SIAW_ADMIN_* en .env.")
    d = js.get("data", {})
    api.token = d.get("token")
    if d.get("debe_cambiar_password"):
        R.out("!! El usuario tiene debe_cambiar_password=1: las rutas darán 403 hasta que cambie la contraseña.", "y")
    return d.get("usuario", {})


# ── Descubrimiento (Swagger) ─────────────────────────────────────────────────
class Resource:
    def __init__(self, name, coll_path):
        self.name, self.coll, self.item = name, coll_path, None
        self.item_param = None
        self.ops = {}  # (kind, method) -> operation

    def op(self, kind, method):
        return self.ops.get((kind, method))


def discover(spec):
    res = {}
    for path, methods in spec["paths"].items():
        m = re.fullmatch(r"/api/([a-z0-9_]+)", path)
        if m and m.group(1) not in SKIP_RESOURCES and "get" in methods and "post" in methods:
            r = res.setdefault(m.group(1), Resource(m.group(1), path))
            for meth, op in methods.items():
                r.ops[("coll", meth)] = op
    for path, methods in spec["paths"].items():
        m = re.fullmatch(r"/api/([a-z0-9_]+)/\{([^}]+)\}", path)
        if m and m.group(1) in res:
            r = res[m.group(1)]
            r.item, r.item_param = path, m.group(2)
            for meth, op in methods.items():
                r.ops[("item", meth)] = op
    return res


def resolve(spec, node):
    while isinstance(node, dict) and "$ref" in node:
        ref = node["$ref"].lstrip("#/").split("/")
        node = spec
        for p in ref:
            node = node[p]
    return node


def body_schema(spec, op):
    if not op or "requestBody" not in op:
        return None
    s = op["requestBody"].get("content", {}).get("application/json", {}).get("schema")
    return resolve(spec, s) if s else None


# ── Generación de datos ──────────────────────────────────────────────────────
def rnd(n=6):
    return "".join(random.choices(string.ascii_lowercase + string.digits, k=n))


def gen_value(prop, name, ctx, tag, variant=0):
    """Valor válido según el schema de Swagger de una propiedad.
    `variant` (0 = registro principal) hace que los registros extra difieran en booleanos, números y fechas,
    para que filtros y orden se prueben contra valores distintos y no contra uno solo."""
    t, fmt = prop.get("type", "string"), prop.get("format")
    if "enum" in prop:
        return random.choice(prop["enum"])
    if t == "boolean":
        return variant % 2 == 0
    if t == "integer":
        lo = max(prop.get("minimum", 1), 1)
        hi = prop.get("maximum")
        return lo + (variant % (hi - lo + 1) if hi is not None and hi >= lo else variant)
    if t == "number":
        return 1.5 + variant
    if t == "array":
        return []
    if t == "object":
        return {}
    if fmt == "uuid":
        return ctx.fk_uuid(name)
    if fmt == "email":
        return f"{tag}_{rnd()}@test.local"
    if fmt == "date":
        return (datetime.now() - timedelta(days=variant)).strftime("%Y-%m-%d")
    if fmt == "date-time":
        return (datetime.now() - timedelta(days=variant)).strftime("%Y-%m-%d %H:%M:%S")
    maxlen = prop.get("maxLength", 20)  # sin maxLength en Swagger: corto para no truncar columnas chicas
    val = f"{tag}{rnd(4)}{name}"
    return val[:maxlen] if len(val) > maxlen else val


class Ctx:
    """Resuelve FKs (uuid): reutiliza un registro existente del padre o crea uno."""

    def __init__(self, api, spec, resources, relations=None, fk_overrides=None):
        self.api, self.spec, self.res = api, spec, resources
        self.relations = relations or {}
        self.overrides = {**FK_MAP, **(fk_overrides or {})}
        self.cur = None  # tabla/recurso cuyo payload se está armando
        self.created = []  # (resource, id) creados para FKs, para limpiar al final
        self._depth = 0

    def parent_for(self, fk_name):
        if fk_name in self.overrides:
            return self.res.get(self.overrides[fk_name])
        related = self.relations.get(self.cur, {}).get(fk_name)  # belongsTo declarado en el modelo
        if related:
            return self.res.get(related)  # sin endpoint propio -> None (fk_uuid lo resuelve consultando la BD)
        base = re.sub(r"_id$", "", fk_name)
        plural = [n for n in self.res if n.endswith("_" + base + "s") or n.endswith("_" + base + "es")]
        exact = [n for n in self.res if n == base or n.endswith("_" + base)]
        cands = plural or exact
        return self.res[sorted(cands, key=len)[0]] if cands else None

    def related_table(self, fk_name):
        return self.relations.get(self.cur, {}).get(fk_name)

    def db_uuid(self, fk_name):
        """Id de un registro real de la tabla relacionada (consulta directa a la BD), validando su formato."""
        table = self.related_table(fk_name)
        if not table or not DB.enabled:
            return None
        rid = DB.existing_id(table)
        if not rid:
            R.out(f"      ! {fk_name}: sin registros en {table}; se envía un uuid inventado", "y")
            return None
        if re.fullmatch(r"[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}", rid):
            fmt = "con guiones"
        elif re.fullmatch(r"[0-9a-fA-F]{32}", rid):
            fmt = "sin guiones"
        else:
            R.out(f"      ! {fk_name}: id de {table} no parece uuid ({rid!r}); se envía un uuid inventado", "y")
            return None
        R.out(f"      {fk_name} <- {table}.id existente ({fmt}): {rid}", "d")
        return rid  # se envía tal como está guardado: es contra lo que valida el exists

    def stored_form(self, fk_name, rid):
        """Si el id que devolvió la API no existe tal cual en la BD, prueba con/sin guiones y usa el guardado
        (es contra lo que el `exists` del Request valida). Avisa de la discrepancia."""
        table = self.related_table(fk_name)
        if not table or not DB.enabled or not rid:
            return rid
        raw = str(rid).replace("-", "")
        dashed = f"{raw[:8]}-{raw[8:12]}-{raw[12:16]}-{raw[16:20]}-{raw[20:]}"
        for cand in dict.fromkeys([str(rid), raw, dashed]):
            if DB.has_id(table, cand):
                if cand != str(rid):
                    kind = "sin guiones" if "-" not in cand else "con guiones"
                    R.out(f"      ! {fk_name}: la API devolvió {rid} pero {table}.id está guardado {kind} ({cand}); "
                          f"se envía el guardado", "y")
                return cand
        return rid

    def fk_uuid(self, fk_name):
        return self.stored_form(fk_name, self._fk_uuid(fk_name))

    def _fk_uuid(self, fk_name):
        parent = self.parent_for(fk_name)
        if not parent or self._depth > 3:
            return self.db_uuid(fk_name) or str(uuid.uuid4())
        # Se crea un padre propio (aísla unicidades tipo "un permiso por menú"); si falla, se reutiliza uno existente.
        self._depth += 1
        try:
            payload = build_payload(self.spec, parent.op("coll", "post"), self, "fk", parent.name)
            code, js = self.api.call("POST", parent.coll, payload)
            if code in (200, 201) and js.get("data", {}).get("id"):
                self.created.append((parent, js["data"]["id"]))
                return js["data"]["id"]
        finally:
            self._depth -= 1
        code, js = self.api.call("GET", parent.coll, query={"per_page": 1, "paginate": True})
        data = js.get("data") if isinstance(js, dict) else None
        if code == 200 and isinstance(data, list) and data and data[0].get("id"):
            return data[0]["id"]
        return self.db_uuid(fk_name) or str(uuid.uuid4())


def build_payload(spec, op, ctx, tag, table=None, only_required=False, variant=0):
    prev, ctx.cur = ctx.cur, table
    try:
        return _build_payload(spec, op, ctx, tag, only_required, variant)
    finally:
        ctx.cur = prev


def _build_payload(spec, op, ctx, tag, only_required, variant=0):
    sch = body_schema(spec, op)
    if not sch:
        return {}
    req = set(sch.get("required", []))
    out = {}
    for name, prop in sch.get("properties", {}).items():
        prop = resolve(spec, prop)
        if only_required and name not in req:
            continue
        if name not in req and prop.get("format") == "uuid":
            par = ctx.parent_for(name)
            if par is not None and par.name == ctx.cur:
                continue  # auto-referencia opcional (árboles, parent_id): se omite
        out[name] = gen_value(prop, name, ctx, tag, variant)
    return out


# ── Migración de la tabla del modelo ────────────────────────────────────────
def find_migrations(table):
    """Archivos de migración que crean `table` (Schema::create con el nombre literal o con $this->table)."""
    found = []
    for f in sorted((ROOT / "database" / "migrations").rglob("*.php")):
        txt = f.read_text(encoding="utf-8", errors="ignore")
        if (re.search(r"Schema::(?:connection\([^)]*\)->)?create\(\s*(?:\$this->table|['\"]%s['\"])" % re.escape(table), txt)
                and re.search(r"['\"]%s['\"]" % re.escape(table), txt)):
            found.append(f)
    return found


def ensure_migrated(table):
    """Valida que la tabla del modelo exista; si no, corre SOLO su migración (artisan migrate --path=...).
    True si se puede seguir probando el modelo."""
    if DB.enabled:
        DB.schemas.pop(table, None)
        if DB.schema_of(table):
            return True
    files = find_migrations(table)
    if not files:
        if DB.enabled:
            R.out(f"    ! la tabla `{table}` no existe y no encontré su migración en database/migrations", "y")
        return True  # sin forma de saberlo / arreglarlo: que decidan las pruebas
    if not APP_CONTAINER:
        R.out(f"    ! no hay contenedor de la app: no puedo correr la migración de `{table}`", "y")
        return True
    for f in files:
        rel = f.relative_to(ROOT).as_posix()
        st = run(["docker", "exec", APP_CONTAINER, "php", "artisan", "migrate:status", f"--path={rel}"])
        if "Pending" not in st.stdout and DB.enabled is False:
            continue  # sin BD para comprobar la tabla: el estado de la migración es la fuente de verdad
        R.out(f"    migración pendiente de `{table}` -> php artisan migrate --path={rel}", "y")
        p = run(["docker", "exec", APP_CONTAINER, "php", "artisan", "migrate", f"--path={rel}", "--force"])
        tail = (p.stdout + p.stderr).strip().splitlines()[-3:]
        if p.returncode != 0:
            R.out(f"    ✗ la migración falló: {' | '.join(tail)}", "r")
            return False
        R.out(f"    migración aplicada: {' | '.join(tail)}", "g")
    if DB.enabled:
        DB.schemas.pop(table, None)
        if not DB.schema_of(table):
            R.out(f"    ✗ tras migrar, la tabla `{table}` sigue sin existir", "r")
            return False
    return True


# ── Tests por modelo ─────────────────────────────────────────────────────────
N_EXTRA = 5  # registros adicionales al principal (total 6) para probar filtros / búsqueda / orden


def scalar_vals(d):
    return {k: v for k, v in d.items() if isinstance(v, (str, int, float, bool)) and v not in (None, "")}


def fetch_list(api, coll, query):
    """Lista completa (recorre páginas si la API pagina). (code, [items])."""
    items, page = [], 1
    while True:
        q = dict(query)
        if page > 1:
            q["page"] = page
        code, js = api.call("GET", coll, query=q)
        if code != 200 or not is_list(js):
            return code, items
        items += js["data"]
        last = (js.get("meta") or {}).get("last_page") or 1
        if page >= last or page >= 50:
            return code, items
        page += 1


def item_path(r, rid):
    return r.item.replace("{" + r.item_param + "}", str(rid))


def is_list(js):
    return isinstance(js, dict) and isinstance(js.get("data"), list)


def test_model(api, spec, ctx, r):
    m = r.name
    R.out(f"\n━━ {m}  [{r.coll}]", "b")
    tag = "t" + rnd(4)
    check = lambda name, cond, detail="": R.check(m, name, cond, detail)

    if not ensure_migrated(m):
        R.check(m, "tabla/migración disponible", False, f"no se pudo crear la tabla `{m}`")
        return

    # LISTAR
    code, js = api.call("GET", r.coll)
    check("GET list", code == 200 and is_list(js), f"-> {code} {str(js)[:150]}")
    if code == 403 or code == 401:
        R.skipped("resto de pruebas", f"sin acceso ({code})")
        return

    code, js = api.call("GET", r.coll, query={"paginate": True, "per_page": 2})
    check("GET list paginate", code == 200 and "meta" in js, f"-> {code}")
    code, js = api.call("GET", r.coll, query={"tiny": True})
    check("GET list tiny", code == 200 and is_list(js), f"-> {code}")

    # CREAR
    post = r.op("coll", "post")
    payload = build_payload(spec, post, ctx, tag, r.name)
    code, js = api.call("POST", r.coll, payload)
    show("POST enviado", payload)
    if code == 403:
        R.skipped("POST create y resto", "403: el usuario no tiene permiso de escritura")
        return
    created = js.get("data") if isinstance(js, dict) else None
    ok = code in (200, 201) and isinstance(created, dict) and created.get("id")
    check("POST create", ok, f"-> {code} {json.dumps(js, ensure_ascii=False)[:300]}  payload={json.dumps(payload, ensure_ascii=False)[:200]}")
    if not ok:
        return
    rid = created["id"]
    show(f"POST respuesta {code}", created)
    if DB.enabled:
        row = DB.row(m, rid)
        if row is False:
            R.skipped("verificación en BD", f"no pude ubicar la tabla `{m}`")
        else:
            check("BD: el registro existe tras crear", bool(row), "no hay fila con ese id")
            if row:
                show(f"BD {DB.schema_of(m)}.{m}", db_row_view(row))
                bad = match_row(payload, row)
                check("BD: valores guardados = enviados", not bad, "; ".join(bad))

    # VALIDACIÓN: sin campos requeridos -> 422
    sch = body_schema(spec, post) or {}
    if sch.get("required"):
        code, js = api.call("POST", r.coll, {})
        check("POST validación (422 sin requeridos)", code == 422, f"-> {code}")

    # MOSTRAR
    if r.op("item", "get"):
        code, js = api.call("GET", item_path(r, rid))
        check("GET show", code == 200 and js.get("data", {}).get("id") == rid, f"-> {code}")
        code, _ = api.call("GET", item_path(r, uuid.uuid4()))
        check("GET show inexistente (404)", code == 404, f"-> {code}")

    # REGISTROS EXTRA: varios registros con valores distintos para que filtros / búsqueda / orden
    # se prueben contra un conjunto y no contra un único registro.
    ours = {rid: created}  # id -> data devuelta por la API al crear
    extra_ids = []
    for i in range(1, N_EXTRA + 1):
        pl = build_payload(spec, post, ctx, f"{tag}{i}", r.name, variant=i)
        c2, j2 = api.call("POST", r.coll, pl)
        d2 = j2.get("data") if isinstance(j2, dict) else None
        if c2 in (200, 201) and isinstance(d2, dict) and d2.get("id"):
            ours[d2["id"]] = d2
            extra_ids.append(d2["id"])
        else:
            R.out(f"      ! registro extra {i} no se creó (-> {c2} {str(j2)[:120]})", "y")
    check(f"POST de {N_EXTRA} registros extra para filtros/orden", len(extra_ids) == N_EXTRA,
          f"solo se crearon {len(extra_ids)} de {N_EXTRA}")
    show("registros de prueba", f"{len(ours)} (principal + {len(extra_ids)} extra)")

    # FILTROS (columna exacta): con el valor del registro principal deben volver exactamente
    # los registros nuestros que comparten ese valor (y no los demás).
    list_params = [resolve(spec, p) for p in r.op("coll", "get").get("parameters", [])]
    for p in list_params:
        pn = p["name"]
        if pn in STD_PARAMS or pn not in created or created[pn] in (None, "") or not isinstance(created[pn], (str, int, float, bool)):
            continue
        val = created[pn]
        expected = {i for i, d in ours.items() if d.get(pn) == val}
        code, items = fetch_list(api, r.coll, {pn: val, "per_page": 100})
        got = {x.get("id") for x in items} & set(ours)
        distinct = len({str(d.get(pn)) for d in ours.values()})
        check(f"GET filtro {pn}=… ({len(expected)} de {len(ours)} coinciden; {distinct} valores distintos)",
              code == 200 and got == expected,
              f"-> {code}; esperados={len(expected)} devueltos={len(got)} sobran={len(got - expected)} faltan={len(expected - got)}")

    # BÚSQUEDA: el término del registro principal lo devuelve a él y NO a los otros.
    if any(p["name"] == "search" for p in list_params):
        term = next((v for v in created.values() if isinstance(v, str) and len(v) > 4 and not re.fullmatch(r"[0-9a-f-]{36}", v)), None)
        if term:
            code, items = fetch_list(api, r.coll, {"search": term, "per_page": 100})
            got = {x.get("id") for x in items} & set(ours)
            others = {i for i, d in ours.items() if i != rid and not any(term.lower() in str(v).lower() for v in d.values())}
            leak = got & others
            check("GET search", code == 200 and rid in got and not leak,
                  f"-> {code} (term={term}) principal={'sí' if rid in got else 'NO'} falsos positivos={len(leak)}")

    # ORDEN: el orden de nuestros registros en la respuesta debe ser el de sus valores (asc y desc).
    if any(p["name"] == "sorts" for p in list_params):
        cols = [k for k, v in created.items() if k != "id" and isinstance(v, (str, int, float)) and not isinstance(v, bool)
                and not re.fullmatch(r"[0-9a-f-]{32,36}", str(v)) and len({str(d.get(k)) for d in ours.values()}) > 1]
        if not cols:
            col = next((k for k, v in created.items() if isinstance(v, (str, int, float)) and k != "id"), None)
            for s_ in ((col, "-" + col) if col else ()):
                code, js = api.call("GET", r.coll, query={"sorts": s_})
                check(f"GET sorts={s_}", code == 200 and is_list(js), f"-> {code}")
            R.skipped("orden verificado por valores", "ninguna columna tiene valores distintos entre los registros")
        for col in cols[:2]:
            numeric = all(isinstance(d.get(col), (int, float)) for d in ours.values())
            key = (lambda v: float(v)) if numeric else (lambda v: str(v).lower())
            for s_, rev in ((col, False), ("-" + col, True)):
                code, items = fetch_list(api, r.coll, {"sorts": s_})
                seq = [key(x[col]) for x in items if x.get("id") in ours and col in x and x[col] is not None]
                want = sorted((key(d[col]) for d in ours.values() if d.get(col) is not None), reverse=rev)
                check(f"GET sorts={s_} (orden verificado en {len(seq)} registros)", code == 200 and len(seq) == len(want) and seq == want,
                      f"-> {code}; obtenido={seq[:8]} esperado={want[:8]}")

    # EDITAR
    put = r.op("item", "put")
    if put and body_schema(spec, put):
        upd = build_payload(spec, put, ctx, tag + "u", r.name)
        code, js = api.call("PUT", item_path(r, rid), upd)
        show("PUT enviado", upd)
        if code == 403:
            R.skipped("PUT update", "403 sin permiso")
        else:
            check("PUT update", code == 200, f"-> {code} {json.dumps(js, ensure_ascii=False)[:300]}")
            if code == 200:
                show("PUT respuesta", js.get("data"))
                row = DB.row(m, rid) if DB.enabled else False
                if row:
                    show("BD tras editar", db_row_view(row))
                    bad = match_row(upd, row)
                    check("BD: valores editados = enviados", not bad, "; ".join(bad))
            if code == 200 and r.op("item", "get"):
                _, js2 = api.call("GET", item_path(r, rid))
                back = js2.get("data", {})
                diff = [k for k, v in upd.items() if isinstance(v, str) and k in back and back[k] != v
                        and not k.endswith("_id") and k not in ("password",)]
                check("PUT persistió cambios", not diff, f"campos distintos: {diff}")
            code, _ = api.call("PUT", item_path(r, uuid.uuid4()), upd)
            check("PUT inexistente (404)", code == 404, f"-> {code}")
    else:
        R.skipped("PUT update", "sin requestBody en Swagger / no existe")

    # ELIMINAR
    if PERM:
        R.skipped("DELETE", "--perm: se conservan los registros creados")
    elif r.op("item", "delete"):
        code, js = api.call("DELETE", item_path(r, rid))
        if code == 403:
            R.skipped("DELETE", "403 sin permiso")
        else:
            check("DELETE", code in (200, 204), f"-> {code} {str(js)[:150]}")
            if code in (200, 204) and DB.enabled:
                row = DB.row(m, rid)
                if row is None:
                    show("BD tras eliminar", "fila eliminada físicamente (hard delete)")
                    check("BD: registro eliminado", True)
                elif row:
                    soft = row.get("deleted_at", "NULL") != "NULL"
                    show("BD tras eliminar", f"soft delete, deleted_at={row.get('deleted_at')}" if soft else db_row_view(row))
                    check("BD: registro eliminado (soft delete)", soft, "la fila sigue activa en BD")
        if code != 403 and r.op("item", "get"):
            code, _ = api.call("GET", item_path(r, rid))
            check("GET tras DELETE (404)", code == 404, f"-> {code}")
    else:
        R.skipped("DELETE", "no existe")

    # Los registros extra: se borran por la API (--undo los purga de la BD)
    if r.op("item", "delete") and not PERM:
        for eid in extra_ids:
            api.call("DELETE", item_path(r, eid))


def snapshot(tables):
    """MAX(pkid) por tabla antes de la corrida: sirve para detectar filas nuevas que el script no creó directamente."""
    snap = {}
    for t in tables:
        schema = DB.schema_of(t)
        if schema:
            v = DB.scalar(f"SELECT COALESCE(MAX(pkid),0) FROM `{schema}`.`{t}`", t)
            if v is not None and v.isdigit():
                snap[t] = int(v)
    return snap


def undo(snap, tok_before, login_user_id):
    """Borra físicamente (también los soft-deleted) todo lo que creó la corrida. No es una transacción:
    cada request HTTP usa su propia conexión, así que se revierte por id, en orden inverso de creación."""
    R.out("\n══════════════ UNDO ══════════════", "b")
    by_table = {}
    for res, rid in reversed(CREATED):
        by_table.setdefault(res, []).append(rid)
    total = 0
    for t, ids in by_table.items():
        n = DB.purge(t, ids)
        total += n or 0
        R.out(f"  {t}: {n if n is not None else '??'} borrados de {len(ids)} creados" +
              (f" ({len(ids) - n} ya no existían: hard delete por la propia API)" if n is not None and n < len(ids) else ""),
              "g" if n is not None else "y")
    # Efectos secundarios: filas nuevas en esas tablas que el script no creó vía POST (se reportan, NO se borran).
    for t, before in snap.items():
        schema = DB.schema_of(t)
        mine = [i for r_, i in CREATED if r_ == t]
        extra = DB.scalar(f"SELECT COUNT(*) FROM `{schema}`.`{t}` WHERE pkid > {before}" +
                          (" AND id NOT IN (" + ",".join(f"'{i}'" for i in mine) + ")" if mine else ""), t)
        if extra and extra != "0":
            R.out(f"  ⚠ {t}: {extra} fila(s) nueva(s) no creadas directamente por el script (efecto secundario o actividad ajena) — no se tocaron", "y")
    # Token de sesión creado por nuestro login
    if tok_before is not None and login_user_id:
        schema = DB.schema_of("siaw_tokens_acceso")
        n = DB.scalar(f"DELETE FROM `{schema}`.`siaw_tokens_acceso` WHERE tokenable_id='{login_user_id}' AND id > {tok_before}; SELECT ROW_COUNT()", "siaw_tokens_acceso")
        R.out(f"  siaw_tokens_acceso: {n} token(s) de la sesión del test borrados", "g")
    R.out(f"UNDO listo: {total} registros eliminados de la BD", "g")


def cleanup(api, ctx):
    if PERM:
        return
    for parent, pid in reversed(ctx.created):
        if parent.op("item", "delete"):
            api.call("DELETE", item_path(parent, pid))


# ── Main ─────────────────────────────────────────────────────────────────────
def main():
    global VERBOSE, PERM
    ap =argparse.ArgumentParser(description="Test dinámico de la API local", add_help=True)
    ap.add_argument("--model", "-m", help="modelos separados por coma")
    ap.add_argument("--txt", nargs="?", const="auto", help="guardar resultados en txt (ruta opcional)")
    ap.add_argument("--list", action="store_true", help="listar modelos detectados")
    ap.add_argument("--no-docker", action="store_true", help="no verificar/levantar Docker")
    ap.add_argument("--no-refresh", action="store_true", help="no regenerar Swagger")
    ap.add_argument("--base-url", help="default: http://localhost:APP_PORT del .env")
    ap.add_argument("--user", "--email", dest="email", metavar="EMAIL_O_CODIGO",
                    help="usuario (email o código de 5 dígitos). Default: env API_TEST_USER o SIAW_ADMIN_EMAIL_INICIAL del .env")
    ap.add_argument("--password", help="contraseña (default: env API_TEST_PASSWORD o SIAW_ADMIN_PASSWORD_INICIAL del .env)")
    ap.add_argument("--ask-password", "-p", action="store_true", help="pedir la contraseña por teclado (no queda en el historial)")
    ap.add_argument("--token", help="usar un Bearer token ya obtenido (omite el login)")
    ap.add_argument("--root", help="raíz del proyecto Laravel (default: se detecta desde el cwd / ubicación del script)")
    ap.add_argument("--app-container", help="contenedor de la app (para regenerar Swagger). Default: el que publica el puerto de la URL base")
    ap.add_argument("--db-container", help="forzar un único contenedor de BD (default: los que publican cada DB_PORT[_X] del .env)")
    ap.add_argument("--fk", action="append", default=[], metavar="CAMPO_ID=RECURSO",
                    help="forzar una relación que no se detecte (repetible). Normalmente innecesario: se leen de los modelos")
    ap.add_argument("--undo", action="store_true",
                    help="al terminar (y tras escribir el txt) borra de la BD todo lo que creó la corrida, incl. soft-deleted. Requiere acceso a la BD")
    ap.add_argument("--clear", action="store_true",
                    help="al terminar hace TRUNCATE de la tabla de cada modelo probado (vacía TODA la tabla, no solo lo creado por la corrida; reinicia pkid). Requiere acceso a la BD. Incompatible con --perm y --undo")
    ap.add_argument("--perm", action="store_true",
                    help="permanente: no elimina los registros que crea (omite DELETE, extras y limpieza de padres). Incompatible con --undo")
    ap.add_argument("--no-db", action="store_true", help="no verificar los datos directamente en la BD")
    ap.add_argument("-v", "--verbose", action="store_true")
    args, extra = ap.parse_known_args()
    VERBOSE = args.verbose
    PERM = args.perm
    if PERM and args.undo:
        sys.exit("--perm y --undo se contradicen: uno conserva los registros y el otro los borra.")
    if args.clear and (PERM or args.undo):
        sys.exit("--clear se contradice con --perm (conserva) y --undo (ya borra lo creado): usa solo uno.")
    global ROOT, APP_CONTAINER, DB_CONTAINER
    ROOT = find_root(args.root)
    base = (args.base_url or f"http://localhost:{read_env('APP_PORT', '8844')}").rstrip("/")
    email = args.email or os.environ.get("API_TEST_USER") or read_env("SIAW_ADMIN_EMAIL_INICIAL", "admin@example.com")
    password = args.password or os.environ.get("API_TEST_PASSWORD")
    if args.ask_password:
        import getpass
        password = getpass.getpass(f"Contraseña de {email}: ")
    if not password and not args.email:  # sin usuario explícito -> el del seeder
        password = read_env("SIAW_ADMIN_PASSWORD_INICIAL", "Cambiar123!")
    if not password and not args.token:
        sys.exit("Indica la contraseña: --password X, --ask-password (-p) o env API_TEST_PASSWORD.")

    started = datetime.now()
    R.out(f"API test — {base} — {started:%Y-%m-%d %H:%M:%S}", "b")
    R.out(f">>> Proyecto: {ROOT}  (.env {'encontrado' if (ROOT / '.env').exists() else 'NO encontrado -> puerto/credenciales por defecto'})", "d")
    if not args.no_docker:
        ensure_docker(base, args.app_container, args.db_container)
    if not args.no_refresh and docker_ready():
        refresh_swagger()

    spec_file = ROOT / "storage" / "api-docs" / "api-docs.json"
    if not spec_file.exists():
        sys.exit("No existe storage/api-docs/api-docs.json (¿Swagger generado?).")
    spec = json.loads(spec_file.read_text(encoding="utf-8"))
    resources = discover(spec)

    if args.list:
        for n in resources:
            print(n)
        return

    # Selección: --model a,b  y/o  --{modelo} sueltos
    wanted = set((args.model or "").split(",")) - {""}
    for e in extra:
        if e.startswith("--"):
            wanted.add(e[2:].replace("-", "_"))
        else:
            sys.exit(f"Argumento desconocido: {e}")
    unknown = wanted - set(resources)
    if unknown:
        sys.exit(f"Modelo(s) no encontrado(s): {', '.join(sorted(unknown))}. Disponibles: {', '.join(resources)}")
    selected = [resources[n] for n in resources if not wanted or n in wanted]

    if not args.no_db and docker_ready():
        servers = discover_db_servers(args.db_container)
        if DB.connect(servers):
            R.out(">>> Verificación en BD activa: " + ", ".join(f"{x['container']} ({x['database'] or '*'})" for x in DB.servers), "g")
        else:
            R.out(">>> No pude conectar a ninguna BD por Docker — solo se verifica por API (usa --db-container)", "y")
    if args.clear and not DB.enabled:
        sys.exit("--clear necesita acceso a la BD (Docker + contenedor de BD). Quita --no-db o revisa --db-container.")
    if args.undo and not DB.enabled:
        sys.exit("--undo necesita acceso a la BD (Docker + contenedor de BD). Quita --no-db o revisa --db-container.")
    api = Api(base)
    kc = keycloak_cfg()
    snap, tok_before, login_uid = {}, None, None
    if args.undo:
        snap = snapshot([r.name for r in selected])
        if not args.token:
            schema = DB.schema_of("siaw_tokens_acceso")
            v = DB.scalar(f"SELECT COALESCE(MAX(id),0) FROM `{schema}`.`siaw_tokens_acceso`", "siaw_tokens_acceso") if schema else None
            tok_before = int(v) if v and v.isdigit() else None
    if args.token:
        api.token = args.token.removeprefix("Bearer ").strip()
        if kc:  # el proyecto no tiene /auth/me: se valida la expiración del JWT, como hace el middleware
            if jwt_claims(api.token).get("exp", 0) < time.time():
                sys.exit("El token está vencido o no es un JWT válido.")
        else:
            code, js = api.call("GET", "/api/auth/me")
            if code != 200:
                sys.exit(f"El token no es válido ({code}).")
        R.out(">>> Usando token provisto", "g")
    elif kc:
        user = login_keycloak(api, kc, email, password)
        R.out(f">>> Login Keycloak OK como {email}", "g")
    else:
        user = login(api, email, password)
        login_uid = user.get("id")
        R.out(f">>> Login OK como {user.get('email', email)}", "g")
    if not kc:
        code, js = api.call("GET", "/api/auth/me")
        rol = (js.get("data", {}) or {}).get("rol") if code == 200 else None
        R.out(f">>> Rol: {rol if rol else 'sin rol / desconocido'}  (403 = SKIP, no FAIL)", "d")

    code, _ = Api(base).call("GET", selected[0].coll, auth=False)
    R.check("global", "sin token -> 401", code == 401, f"-> {code}")

    relations = load_relations() if docker_ready() and not args.no_docker else {}
    n_rel = sum(len(v) for v in relations.values())
    R.out(f">>> Relaciones (belongsTo/hasMany) leídas de los modelos: {n_rel}" if n_rel else
          ">>> No pude leer las relaciones de los modelos — uso heurística por nombre (puedes forzar con --fk campo_id=recurso)",
          "d" if n_rel else "y")
    overrides = dict(x.split("=", 1) for x in args.fk if "=" in x)
    ctx = Ctx(api, spec, resources, relations, overrides)
    try:
        for r in selected:
            try:
                test_model(api, spec, ctx, r)
            except Exception as e:  # un modelo roto no debe frenar al resto
                R.check(r.name, "ejecución sin excepción", False, repr(e))
    except KeyboardInterrupt:
        R.out("\n!! Interrumpido por el usuario", "y")
    cleanup(api, ctx)

    secs = (datetime.now() - started).total_seconds()
    R.out("\n══════════════ RESUMEN ══════════════", "b")
    R.out(f"Modelos: {len(selected)}   OK: {R.ok}   FAIL: {R.fail}   SKIP: {R.skip}   ({secs:.1f}s)",
          "g" if not R.fail else "r")
    for f in R.failures:
        R.out(f"  ✗ {f}", "r")

    if args.txt:
        path = Path(args.txt) if args.txt != "auto" else ROOT / "tests" / "results" / f"api_test_{started:%Y%m%d_%H%M%S}.txt"
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text("\n".join(R.lines) + "\n", encoding="utf-8")
        print(f"Resultados guardados en {path}")
    if args.undo:
        undo(snap, tok_before, login_uid)
        if args.txt:  # el resumen del undo también queda en el txt
            path.write_text("\n".join(R.lines) + "\n", encoding="utf-8")
    if args.clear:
        R.out("\n══════════════ CLEAR (TRUNCATE) ══════════════", "b")
        for r in reversed(selected):
            n = DB.truncate(r.name)
            R.out(f"  {r.name}: " + (f"TRUNCATE ok ({n} filas eliminadas)" if n is not None else "no se pudo truncar"),
                  "g" if n is not None else "r")
        if args.txt:
            path.write_text("\n".join(R.lines) + "\n", encoding="utf-8")
    sys.exit(1 if R.fail else 0)


if __name__ == "__main__":
    main()
