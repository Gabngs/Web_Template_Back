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
  python tests/api_test.py                       # todos los modelos
  python tests/api_test.py --lcs_catalogo        # solo ese modelo (puedes poner varios)
  python tests/api_test.py --model lcs_catalogo,siaw_roles
  python tests/api_test.py --user otro@mail.com -p        # otro usuario, pide la contraseña por teclado
  python tests/api_test.py --user 00002 --password X      # por código de usuario
  python tests/api_test.py --token "KEY@1|abc..."         # con un token ya obtenido (sin login)
  (env: API_TEST_USER / API_TEST_PASSWORD también sirven)
  python tests/api_test.py --txt                 # además guarda tests/results/api_test_<fecha>.txt
  python tests/api_test.py --txt salida.txt
  python tests/api_test.py --undo                # al terminar borra de la BD todo lo que creó (tras escribir el txt)
  python tests/api_test.py --list                # lista los modelos detectados
  python tests/api_test.py --no-docker --no-refresh -v
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
from datetime import datetime
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
APP_CONTAINER = os.environ.get("API_TEST_APP_CONTAINER", "lcs_laravel_backend")
DB_CONTAINER = "lcs_db"
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


def load_relations():
    """Relaciones (belongsTo + hasMany/hasOne) leídas de los modelos del proyecto. {} si no se pueden leer."""
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


# ── Docker ───────────────────────────────────────────────────────────────────
def docker_ready():
    return shutil.which("docker") is not None and run(["docker", "info"]).returncode == 0


def ensure_docker(base):
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

    needed = [APP_CONTAINER, DB_CONTAINER]
    running = lambda n: run(["docker", "inspect", "-f", "{{.State.Running}}", n]).stdout.strip() == "true"
    if not all(running(n) for n in needed):
        R.out(">>> Contenedores del proyecto caídos — ejecutando ./start.sh ...", "y")
        bash = shutil.which("bash")
        if not (ROOT / "start.sh").exists():
            sys.exit(f"Faltan contenedores ({', '.join(needed)}) y no hay start.sh en {ROOT}. Levántalos a mano o usa --no-docker.")
        if not bash:
            sys.exit("No encuentro bash (Git Bash) para ejecutar start.sh. Levanta el proyecto manualmente.")
        p = subprocess.run([bash, "start.sh"], cwd=ROOT)
        if p.returncode != 0:
            R.out(">>> start.sh terminó con error — sigo si la API responde (en el 1er arranque la BD puede tardar).", "y")
    R.out(">>> Contenedores del proyecto arriba", "g")

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
    R.out(">>> Regenerando Swagger (l5-swagger:generate)...", "d")
    p = run(["docker", "exec", APP_CONTAINER, "php", "artisan", "l5-swagger:generate"])
    if p.returncode != 0:
        R.out(f"    no se pudo regenerar ({p.stderr.strip()[:200]}), uso el JSON existente", "y")


# ── Base de datos (verificación directa en el contenedor) ───────────────────
class Db:
    """Consulta la BD con el cliente mariadb/mysql del contenedor de BD (docker exec)."""

    def __init__(self):
        self.container, self.user, self.pwd = None, None, None
        self.client, self.schemas, self.enabled = None, {}, False

    def _exec(self, sql):
        for client in ([self.client] if self.client else ["mariadb", "mysql"]):
            p = run(["docker", "exec", "-e", f"MYSQL_PWD={self.pwd}", self.container, client,
                     "-u", self.user, "-B", "--default-character-set=utf8mb4", "-e", sql])
            if p.returncode == 0:
                self.client = client
                return p.stdout
        return None

    def connect(self):
        self.enabled = self._exec("SELECT 1") is not None
        return self.enabled

    def schema_of(self, table):
        if table not in self.schemas:
            assert re.fullmatch(r"[A-Za-z0-9_]+", table)
            out = self._exec(f"SELECT table_schema FROM information_schema.tables WHERE table_name='{table}'")
            lines = (out or "").strip().splitlines()[1:]
            self.schemas[table] = lines[0] if lines else None
        return self.schemas[table]

    def scalar(self, sql):
        out = self._exec(sql)
        lines = (out or "").strip().splitlines()
        return lines[1] if len(lines) > 1 else None

    def purge(self, table, ids):
        """DELETE físico por uuid. Devuelve filas borradas (o None si falló)."""
        schema = self.schema_of(table)
        ids = [i for i in ids if re.fullmatch(r"[0-9a-fA-F-]{36}", str(i))]
        if not schema or not ids:
            return None
        lst = ",".join(f"'{i}'" for i in ids)
        n = self.scalar(f"DELETE FROM `{schema}`.`{table}` WHERE id IN ({lst}); SELECT ROW_COUNT()")
        return int(n) if n and n.isdigit() else None

    def row(self, table, rid):
        """dict con la fila (incluye deleted_at si existe), None si no hay fila, False si no se pudo consultar."""
        schema = self.schema_of(table)
        if not schema or not re.fullmatch(r"[0-9a-fA-F-]{36}", str(rid)):
            return False
        out = self._exec(f"SELECT * FROM `{schema}`.`{table}` WHERE id='{rid}'")
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


def gen_value(prop, name, ctx, tag):
    """Valor válido según el schema de Swagger de una propiedad."""
    t, fmt = prop.get("type", "string"), prop.get("format")
    if "enum" in prop:
        return random.choice(prop["enum"])
    if t == "boolean":
        return True
    if t == "integer":
        return max(prop.get("minimum", 1), 1)
    if t == "number":
        return 1.5
    if t == "array":
        return []
    if t == "object":
        return {}
    if fmt == "uuid":
        return ctx.fk_uuid(name)
    if fmt == "email":
        return f"{tag}_{rnd()}@test.local"
    if fmt == "date":
        return datetime.now().strftime("%Y-%m-%d")
    if fmt == "date-time":
        return datetime.now().strftime("%Y-%m-%d %H:%M:%S")
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
            return self.res.get(related)
        base = re.sub(r"_id$", "", fk_name)
        plural = [n for n in self.res if n.endswith("_" + base + "s") or n.endswith("_" + base + "es")]
        exact = [n for n in self.res if n == base or n.endswith("_" + base)]
        cands = plural or exact
        return self.res[sorted(cands, key=len)[0]] if cands else None

    def fk_uuid(self, fk_name):
        parent = self.parent_for(fk_name)
        if not parent or self._depth > 3:
            return str(uuid.uuid4())
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
        return str(uuid.uuid4())


def build_payload(spec, op, ctx, tag, table=None, only_required=False):
    prev, ctx.cur = ctx.cur, table
    try:
        return _build_payload(spec, op, ctx, tag, only_required)
    finally:
        ctx.cur = prev


def _build_payload(spec, op, ctx, tag, only_required):
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
        out[name] = gen_value(prop, name, ctx, tag)
    return out


# ── Tests por modelo ─────────────────────────────────────────────────────────
def item_path(r, rid):
    return r.item.replace("{" + r.item_param + "}", str(rid))


def is_list(js):
    return isinstance(js, dict) and isinstance(js.get("data"), list)


def test_model(api, spec, ctx, r):
    m = r.name
    R.out(f"\n━━ {m}  [{r.coll}]", "b")
    tag = "t" + rnd(4)
    check = lambda name, cond, detail="": R.check(m, name, cond, detail)

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

    # FILTROS (columna exacta, tomando el valor del registro creado)
    list_params = [resolve(spec, p) for p in r.op("coll", "get").get("parameters", [])]
    for p in list_params:
        pn = p["name"]
        if pn in STD_PARAMS or pn not in created or created[pn] in (None, ""):
            continue
        code, js = api.call("GET", r.coll, query={pn: created[pn], "per_page": 100})
        found = code == 200 and is_list(js) and any(x.get("id") == rid for x in js["data"])
        check(f"GET filtro {pn}=…", found, f"-> {code}, registro no devuelto")

    # BÚSQUEDA
    if any(p["name"] == "search" for p in list_params):
        term = next((v for v in created.values() if isinstance(v, str) and len(v) > 4 and not re.fullmatch(r"[0-9a-f-]{36}", v)), None)
        if term:
            code, js = api.call("GET", r.coll, query={"search": term, "per_page": 100})
            found = code == 200 and is_list(js) and any(x.get("id") == rid for x in js["data"])
            check("GET search", found, f"-> {code} (term={term})")

    # ORDEN
    if any(p["name"] == "sorts" for p in list_params):
        col = next((k for k, v in created.items() if isinstance(v, (str, int, float)) and k != "id"), None)
        if col:
            for s in (col, "-" + col):
                code, js = api.call("GET", r.coll, query={"sorts": s})
                check(f"GET sorts={s}", code == 200 and is_list(js), f"-> {code}")

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
    if r.op("item", "delete"):
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


def snapshot(tables):
    """MAX(pkid) por tabla antes de la corrida: sirve para detectar filas nuevas que el script no creó directamente."""
    snap = {}
    for t in tables:
        schema = DB.schema_of(t)
        if schema:
            v = DB.scalar(f"SELECT COALESCE(MAX(pkid),0) FROM `{schema}`.`{t}`")
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
                          (" AND id NOT IN (" + ",".join(f"'{i}'" for i in mine) + ")" if mine else ""))
        if extra and extra != "0":
            R.out(f"  ⚠ {t}: {extra} fila(s) nueva(s) no creadas directamente por el script (efecto secundario o actividad ajena) — no se tocaron", "y")
    # Token de sesión creado por nuestro login
    if tok_before is not None and login_user_id:
        schema = DB.schema_of("siaw_tokens_acceso")
        n = DB.scalar(f"DELETE FROM `{schema}`.`siaw_tokens_acceso` WHERE tokenable_id='{login_user_id}' AND id > {tok_before}; SELECT ROW_COUNT()")
        R.out(f"  siaw_tokens_acceso: {n} token(s) de la sesión del test borrados", "g")
    R.out(f"UNDO listo: {total} registros eliminados de la BD", "g")


def cleanup(api, ctx):
    for parent, pid in reversed(ctx.created):
        if parent.op("item", "delete"):
            api.call("DELETE", item_path(parent, pid))


# ── Main ─────────────────────────────────────────────────────────────────────
def main():
    global VERBOSE
    ap = argparse.ArgumentParser(description="Test dinámico de la API local", add_help=True)
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
    ap.add_argument("--app-container", help="contenedor de la app (para regenerar Swagger). Default: lcs_laravel_backend")
    ap.add_argument("--db-container", help="contenedor de la BD (default: DB_LCS_HOST/DB_HOST del .env, o lcs_db)")
    ap.add_argument("--fk", action="append", default=[], metavar="CAMPO_ID=RECURSO",
                    help="forzar una relación que no se detecte (repetible). Normalmente innecesario: se leen de los modelos")
    ap.add_argument("--undo", action="store_true",
                    help="al terminar (y tras escribir el txt) borra de la BD todo lo que creó la corrida, incl. soft-deleted. Requiere acceso a la BD")
    ap.add_argument("--no-db", action="store_true", help="no verificar los datos directamente en la BD")
    ap.add_argument("-v", "--verbose", action="store_true")
    args, extra = ap.parse_known_args()
    VERBOSE = args.verbose
    global ROOT, APP_CONTAINER, DB_CONTAINER
    ROOT = find_root(args.root)
    if args.app_container:
        APP_CONTAINER = args.app_container
    DB_CONTAINER = args.db_container or read_env("DB_LCS_HOST") or read_env("DB_HOST") or "lcs_db"
    if DB_CONTAINER in ("127.0.0.1", "localhost", "mariadb", "mysql"):
        DB_CONTAINER = "lcs_db"

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
        ensure_docker(base)
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
        DB.container, DB.user, DB.pwd = DB_CONTAINER, read_env("DB_USERNAME", "sail"), read_env("DB_PASSWORD", "")
        if DB.connect():
            R.out(f">>> Verificación en BD activa (contenedor {DB_CONTAINER})", "g")
        else:
            R.out(f">>> No pude conectar a la BD en el contenedor '{DB_CONTAINER}' — solo se verifica por API (usa --db-container)", "y")
    if args.undo and not DB.enabled:
        sys.exit("--undo necesita acceso a la BD (Docker + contenedor de BD). Quita --no-db o revisa --db-container.")
    api = Api(base)
    snap, tok_before, login_uid = {}, None, None
    if args.undo:
        snap = snapshot([r.name for r in selected])
        if not args.token:
            schema = DB.schema_of("siaw_tokens_acceso")
            v = DB.scalar(f"SELECT COALESCE(MAX(id),0) FROM `{schema}`.`siaw_tokens_acceso`") if schema else None
            tok_before = int(v) if v and v.isdigit() else None
    if args.token:
        api.token = args.token.removeprefix("Bearer ").strip()
        code, js = api.call("GET", "/api/auth/me")
        if code != 200:
            sys.exit(f"El token no es válido ({code}).")
        user = js.get("data", {})
        R.out(">>> Usando token provisto", "g")
    else:
        user = login(api, email, password)
        login_uid = user.get("id")
        R.out(f">>> Login OK como {user.get('email', email)}", "g")
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
    sys.exit(1 if R.fail else 0)


if __name__ == "__main__":
    main()
