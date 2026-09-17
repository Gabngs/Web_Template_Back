# Configuración: bases de datos, migraciones/seeders centralizados y rutas CRUD

Mapeo de cómo está armado este proyecto (LCS — Laravel Core Standard) en tres frentes:
1. Conexiones a base de datos (`dblcs`, `dbsiaw`, `dbsincro`)
2. Cómo las migraciones y seeders de todos los módulos terminan centralizados en un único ledger
3. Cómo se registran las rutas y el middleware de seguridad centralizado

Ver también el estándar completo en el Vault: `Template Estandar Backend/Service Patron/Conexiones, Migraciones y Rutas.md`.

---

## 1. Conexiones a base de datos

Definidas en [config/database.php](config/database.php). El proyecto nace con **3 conexiones MariaDB fijas**, todas apuntando (por defecto) al mismo host pero a bases de datos separadas:

| Conexión Laravel | Env prefix    | Base física  | Contenido                                              |
|-------------------|---------------|--------------|---------------------------------------------------------|
| `dblcs`           | `DB_LCS_*`    | `db_lcs`     | Negocio propio del proyecto (tablas `{prefijo}_*`) — vacía hasta que se agregue el primer módulo |
| `dbsiaw`          | `DB_SIAW_*`   | `db_siaw`    | Usuarios/auth/roles/permisos/menús (tablas `siaw_*`) — **es la conexión `default`** |
| `dbsincro`        | `DB_SINCRO_*` | `db_sincro`  | Infraestructura: sessions, jobs, cache, **y el ledger de migraciones + `seeders_log`** |

Regla dura del estándar: el nombre de conexión es literal `db{prefijo}` — nunca `mysql_db{prefijo}` ni variantes con el motor en el nombre. El driver (`mariadb`) es un detalle de cada entrada en `config/database.php`, no algo que el resto del código necesite ver.

Notas:
- `DB_RUNTIME_USER`/`DB_RUNTIME_PASS` pisan las credenciales per-conexión cuando están definidas (usadas por el Job de migración en k8s, que corre con un usuario con permisos DDL — ver `k8s/05-job-migrate.yaml`).
- `sqlite` solo se usa para tests (`phpunit.xml`, en memoria).
- Un módulo de negocio nuevo **no** abre una conexión propia — todas las tablas de negocio de este proyecto viven en `dblcs`, sin importar cuántos módulos/carpetas de migración tenga. `dbsiaw` y `dbsincro` son las únicas dos conexiones "especiales".

---

## 2. Migraciones centralizadas

### Estructura en disco

Cada módulo tiene su propia carpeta bajo `database/migrations/`, y cada migración fija su propia conexión con `protected $connection`:

```
database/migrations/
├── Siaw/      → protected $connection = 'dbsiaw';
├── Sincro/    → protected $connection = 'dbsincro';
└── {Modulo}/  → protected $connection = 'dblcs';   (el primer módulo de negocio que se agregue)
```

### Cómo se cargan todas a la vez

[app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php), `boot()`:

```php
// Cada subcarpeta de database/migrations/ es un módulo con su propia conexión.
$this->loadMigrationsFrom(glob(database_path('migrations/*'), GLOB_ONLYDIR));
```

Esto reemplaza el registro manual de cada módulo: **agregar un módulo nuevo = crear una carpeta nueva**, sin tocar este archivo ni el YAML de deploy (`k8s/05-job-migrate.yaml` corre `php artisan migrate --force` sin flags, así que un módulo nuevo se recoge solo).

### Por qué el ledger no queda repartido en 3 bases

Por defecto, Laravel guarda la tabla `migrations` (el ledger de "qué ya corrió") en la conexión de cada migración — es decir, terminarías con una tabla `migrations` distinta en `db_lcs`, otra en `db_siaw`, otra en `db_sincro`. Eso se evita reemplazando el *binding* del repositorio de migraciones.

[app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php), `register()`:

```php
$this->app->extend('migration.repository', function ($repository, $app) {
    $migrations = $app['config']['database.migrations'];
    $table = is_array($migrations) ? ($migrations['table'] ?? 'migrations') : $migrations;

    return new CentralMigrationRepository($app['db'], $table);
});
```

[app/Database/CentralMigrationRepository.php](app/Database/CentralMigrationRepository.php):

```php
class CentralMigrationRepository extends DatabaseMigrationRepository
{
    public const LEDGER_CONNECTION = 'dbsincro';

    public function setSource($name)
    {
        parent::setSource(self::LEDGER_CONNECTION);   // ignora la conexión de la migración...
    }

    public function getConnection()
    {
        return $this->resolver->connection(self::LEDGER_CONNECTION); // ...y fuerza dbsincro
    }
}
```

Efecto: sin importar en qué conexión corre cada `up()`/`down()`, la fila que registra "esta migración ya corrió" siempre se escribe en la tabla `migrations` de `dbsincro`. Un solo `php artisan migrate --force` (sin flags, sin `--database`) migra las 3 bases y deja un único historial consultable.

---

## 3. Seeders centralizados vía `seeders_log`

Problema que resuelve: `php artisan db:seed --force` corre **en cada deploy** ([k8s/05-job-migrate.yaml](k8s/05-job-migrate.yaml)). Sin control, cada seeder no-idempotente duplicaría filas en cada push a `main`.

### Tabla de control

Migración `database/migrations/Sincro/..._create_seeders_log_table.php` — vive en `dbsincro` igual que el ledger de migraciones:

```php
protected $connection = 'dbsincro';

Schema::connection($this->connection)->create('seeders_log', function (Blueprint $table) {
    $table->id();
    $table->string('seeder')->unique();
    $table->timestamp('ran_at');
});
```

### Lógica en `DatabaseSeeder`

[database/seeders/DatabaseSeeder.php](database/seeders/DatabaseSeeder.php):

```php
$log        = DB::connection('dbsincro')->table('seeders_log');
$yaCorridos = $log->pluck('seeder')->all();

foreach ($seeders as $seederClass) {
    if (in_array($seederClass, $yaCorridos, true)) {
        continue;
    }

    $this->call($seederClass);

    $log->insert(['seeder' => $seederClass, 'ran_at' => now()]);
}
```

`$seeders` es un array ordenado a mano (el orden importa: roles → usuario admin → catálogo de modelos → permisos → sistema base → menús). **Agregar un seeder nuevo = agregarlo al final del array** — en el siguiente deploy solo ese corre, porque el resto ya está en `seeders_log`.

El proyecto nace con los 10 seeders base de SIAW: roles (SuperUser/Admin), usuario admin, catálogo de content models/permisos, sistema base `LCS`, y el árbol inicial de `siaw_menus` (Dashboard + Parámetros del sistema con sus 5 sub-ítems). Un módulo de negocio nuevo agrega sus propios seeders al final de ese mismo array.

---

## 4. Rutas: `RouteServiceProvider` con el stack de seguridad centralizado

### Cómo se registran las rutas

[app/Providers/RouteServiceProvider.php](app/Providers/RouteServiceProvider.php) autoincluye cada archivo de `routes/modules/` (uno por recurso) dentro de un único grupo que ya exige el stack de seguridad completo — ningún archivo de módulo repite `prefix('api')` ni el middleware de auth:

```php
$files = glob(base_path('routes/modules') . DIRECTORY_SEPARATOR . '*.php') ?: [];

Route::prefix('api')
    ->middleware(['session.key', 'auth:sanctum', 'throttle:api', 'forzar.cambio'])
    ->group(function () use ($files) {
        foreach ($files as $file) {
            require $file;
        }
    });
```

Agregar un módulo = crear `routes/modules/{modulo}.php`, sin tocar el provider. Cada archivo de módulo solo declara sus rutas y, si le hace falta, el sub-grupo `->middleware('permiso:isSuperUser,isAdmin')` (u otro slug) para sus rutas sensibles — ver ejemplo en [routes/modules/siaw_usuarios.php](routes/modules/siaw_usuarios.php).

Las rutas **públicas** de auth (`/auth/public-key`, `/auth/challenge`, `/auth/login`) NO viven en `routes/modules/` — están en [routes/api/api.php](routes/api/api.php), cargadas por `bootstrap/app.php` vía `withRouting(api: ...)`, fuera de este grupo protegido.

### Permisos en rutas — cuándo mapear y cuándo no

El CRUD básico (`index`/`show`) no lleva middleware de permiso — cualquier usuario autenticado puede leer, y es el frontend el que oculta/deshabilita según los permisos que trae el usuario. Las rutas de escritura (`store`/`update`/`destroy`) sí llevan `->middleware('permiso:isSuperUser,isAdmin')` explícito, porque confiar solo en que el frontend oculte el botón no alcanza: cualquiera con un token válido puede llamar al endpoint directo (Postman, curl).

Ver el detalle completo (incluida la regla de orden de rutas y por qué esto no lo resuelve CORS) en el Vault: `Template Estandar Backend/Service Patron/Conexiones, Migraciones y Rutas.md`.
