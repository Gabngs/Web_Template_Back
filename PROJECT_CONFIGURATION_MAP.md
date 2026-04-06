# Mapeo de Configuración del Proyecto - Peru Lex Backend

## 📋 Índice

1. [Arquitectura General](#arquitectura-general)
2. [Stack Tecnológico](#stack-tecnológico)
3. [Estructura de Archivos por Módulo](#estructura-de-archivos-por-módulo)
4. [Patrón de Base de Datos](#patrón-de-base-de-datos)
5. [Sistema de Permisos](#sistema-de-permisos)
6. [Convenciones de Código](#convenciones-de-código)
7. [Sistema de Rutas](#sistema-de-rutas)
8. [Helpers y Traits](#helpers-y-traits)
9. [Frontend Integration](#frontend-integration)
10. [Automatización Propuesta](#automatización-propuesta)

---

## 1. Arquitectura General

### Patrón Arquitectónico

- **Patrón**: Service Layer Pattern
- **Controller**: Orquestador únicamente (no contiene lógica de negocio)
- **Service**: Contiene toda la lógica de negocio y validaciones
- **Model**: Define relaciones, scopes y casts
- **Filter**: Maneja filtros dinámicos para queries
- **Request**: Validación de entrada (Base, Store, Update)
- **Resource**: Transformación y serialización de salida

### Flujo de Datos

```
Request → Controller → Service → Model → Database
                ↓
             Response ← Resource ← Service ← Model
```

---

## 2. Stack Tecnológico

### Backend

- **Framework**: Laravel 12
- **Autenticación**: Laravel Sanctum (tokens Bearer con expiración de 30 minutos)
- **Base de Datos**: MySQL/MariaDB
- **Documentación**: Swagger/OpenAPI (L5-Swagger - versión estable del proyecto)
- **Contenedores**: Docker + Docker Compose
- **Control de Versiones**: Git + GitHub Actions para CI/CD a rama `main`

### Dependencias Clave

- **PHP**: 8.4
- **Composer**: Gestión de dependencias backend
- **Laravel Sanctum**: Autenticación con tokens Bearer (recomendado para este proyecto)
- **Soft Deletes**: Eliminación lógica estándar en TODAS las tablas
- **Jinja2** (Python): Para templates del generador de módulos

### Autenticación y Seguridad

#### Sanctum

- **Tokens**: Bearer con expiración automática de **30 minutos**
- **Obtener Usuario Autenticado**: `auth()->id()` retorna el ID (BIGINT) del usuario logueado
    ```php
    $userId = auth()->id(); // BIGINT del usuario autenticado (users.id)
    ```
- **Middleware**: `auth:sanctum` para **todas** las rutas protegidas
- **Rate Limiting**: 50,000 peticiones/minuto por usuario/IP (configurado en `RouteServiceProvider`)

#### Consideraciones Futuras

- **Tokens por Terceros**: Idealmente manejar tokens en contenedor separado (crear middleware `ApiToken` personalizado)
- **Limitación Actual**: Por falta de presupuesto se usa Sanctum estándar de Laravel
- **Escalabilidad**: Migrar a servicio de autenticación dedicado cuando haya recursos

### CI/CD con GitHub Actions

- **Rama principal**: `main` (protegida)
- **Desarrollo**: NUNCA trabajar directamente en `main`, usar `dev` u otras ramas
- **Deploy**: Automático con `git push` a `main` mediante GitHub Actions
- **Runner**: Auto-alojado en servidor (configurado en `.github/workflows/deploy.yml`)
- **Swagger**: Regeneración automática con `php artisan l5-swagger:generate` en deploy

---

## 3. Estructura de Archivos por Módulo

### Stack Completo por Entidad

Para cada módulo/entidad se crean **automáticamente** mediante script Python los siguientes archivos:

```
database/migrations/
└── YYYY_MM_DD_HHMMSS_create_nombre_tabla_table.php

app/
├── Models/
│   └── NombreModelo.php                          # Modelo Eloquent
├── Filters/
│   └── NombreModeloFilter.php                    # Filtros dinámicos (QueryFilter)
├── Http/
│   ├── Requests/
│   │   ├── BaseNombreModeloRequest.php           # Mensajes de validación compartidos
│   │   ├── StoreNombreModeloRequest.php          # Validación para create
│   │   └── UpdateNombreModeloRequest.php         # Validación para update
│   ├── Resources/
│   │   └── NombreModeloResource.php              # Transformación de salida API
│   └── Controllers/
│       └── Api/
│           └── NombreModeloController.php        # Orquestador CRUD (NO lógica)
├── Services/
│   └── NombreModeloService.php                   # Lógica de negocio COMPLETA
│
routes/modules/
└── nombre_modelos.php                            # Rutas del módulo (auth:sanctum)

storage/api-docs/
└── api-docs.json                                 # Swagger (auto-generado)
```

### Orden de Creación Recomendado

1. **Migración** (SIEMPRE usar `php artisan make:migration create_nombre_tabla_table`)
2. **Model** (con traits: `HasFilters`, `SoftDeletes`, opcionalmente `ResolvesUuidToPkid`)
3. **Filter** (extiende `QueryFilter` con `allowedFilters`)
4. **Requests** (Base → Store → Update en ese orden)
5. **Resource** (transformación usando `whenLoaded` para relaciones)
6. **Service** (lógica + `resolveIds` + auditoría automática)
7. **Controller** (solo orquestación + trait `ApiResponse`)
8. **Route File** (middleware `auth:sanctum` + `apiResource`)
9. **Swagger** (auto-generado ejecutando `php artisan l5-swagger:generate`)

### Características Clave de Cada Componente

#### 1. Migración

- ✅ Usar **SIEMPRE** `php artisan make:migration` (evita problemas de nomenclatura)
- ✅ Incluir campos de auditoría obligatorios:
    - `created_by_id BIGINT UNSIGNED NULL` → Quien creó (FK a users.id)
    - `updated_by_id BIGINT UNSIGNED NULL` → Quien actualizó por última vez (FK a users.id)
    - `deleted_by_id BIGINT UNSIGNED NULL` → **NUEVO**: Quien eliminó (FK a users.id)
- ✅ Incluir timestamps: `created_at`, `updated_at`
- ✅ Incluir `deleted_at` para SoftDeletes
- ⚠️ **NO usar FOREIGN KEY explícitas** en migraciones
    - Razón: Evitar errores al eliminar registros
    - SoftDeletes ya protege integridad referencial
    - Relaciones se definen solo en modelos
- 📌 **UUID + PKID**: Solo para tablas de negocio (no para `users`, `roles`, etc.)

#### 2. Model

- Extender `BaseModel` si usa UUID, `Model` si usa ID autoincremental
- **Traits obligatorios**:
    - `HasFilters` (para scope `filter()`)
    - `SoftDeletes` (eliminación lógica)
    - `ResolvesUuidToPkid` (opcional, solo en Services)
- Definir relaciones (`BelongsTo`, `HasMany`) **sin FK explícitas**
- Scopes personalizados según lógica de negocio
- **Scope Global**: Crear uno global reutilizable para evitar repetición por modelo

#### 3. Filter

- Extender `QueryFilter` de `app/Filters/QueryFilter.php`
- Declarar array `$allowedFilters` con campos permitidos
- Early return si `$value === ''` en cada método de filtro
- **Filtros estándar** que deben estar en TODOS los módulos:
    - `created_at` / `created_at_from` / `created_at_to`
    - `updated_at` / `updated_at_from` / `updated_at_to`
    - `deleted_at` (solo al usar `withTrashed()`)
    - `created_by_id`, `updated_by_id`, `deleted_by_id`

#### 4. Service

- Usar trait `ResolvesUuidToPkid` para conversión UUID → PKID
- Métodos estándar: `crear()`, `actualizar()`, `eliminar()`, `restaurar()`
- **Auditoría automática** en cada método:

    ```php
    // Crear
    $data['created_by_id'] = (string) auth()->id();
    $data['updated_by_id'] = (string) auth()->id();

    // Actualizar
    $data['updated_by_id'] = (string) auth()->id();

    // Eliminar (soft delete)
    $modelo->update(['deleted_by_id' => (string) auth()->id()]);
    $modelo->delete();
    ```

- Resolver UUIDs a PKIDs antes de guardar (usando `resolveIds()`)
- Transacciones DB (`DB::transaction`) cuando sea necesario
- **Toda la lógica de negocio** va aquí, NO en el controller

#### 5. Controller

- **Solo orquestación** (NO lógica de negocio)
- Trait obligatorio: `ApiResponse`
- Dependency injection del Service en constructor
- Cargar relaciones con `->load()` ANTES de retornar
- Route model binding siempre que sea posible
- Documentación Swagger en cada método público
- Métodos estándar: `index`, `show`, `store`, `update`, `destroy`, `restore`

#### 6. Route File

- Middleware: `auth:sanctum` para todas las rutas protegidas
- Rutas extra (bulk, export, etc.) **ANTES** de `apiResource`
- Usar **SIEMPRE** `apiResource` para CRUD estándar
- Ubicación fija: `routes/modules/nombre_modelos.php`
- Auto-cargado desde `routes/api.php` con `require __DIR__ . '/modules/nombre_modelos.php';`

---

## 4. Patrón de Base de Datos

### Estructura de Tabla Estándar

#### Tablas del Sistema (users, roles, permissions, etc.)

**Usan solo ID autoincremental** - Generadas por Artisan:

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol_id BIGINT UNSIGNED NULL,  -- FK a roles
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    display_name VARCHAR(255) NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by_id BIGINT UNSIGNED NULL,  -- FK a users.id (quien creó)
    updated_by_id BIGINT UNSIGNED NULL,  -- FK a users.id (quien actualizó)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE content_model (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ap_label VARCHAR(255) NOT NULL,  -- Prefijo del cliente (epl, sl, crm)
    ap_model VARCHAR(255) NOT NULL,  -- Nombre del modelo (alumnos, cursos)
    ap_table VARCHAR(255) NOT NULL   -- Nombre de tabla completo (epl_alumnos)
);
```

**Características**:

- Solo `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- Sin `pkid` separado
- `content_model` **NO tiene** campos de auditoría ni timestamps (es metadata del sistema)
- `created_by_id` y `updated_by_id` en otras tablas apuntan a `users.id` (autoincrement)

---

#### Tablas de Negocio (alumnos, cursos, productos, etc.)

**Usan UUID + PKID dual key system** - Para routing y relaciones:

```sql
CREATE TABLE prefijo_nombre_tabla (
    pkid BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- Para FKs entre tablas
    id VARCHAR(36) UNIQUE NOT NULL,                   -- UUID para routing/binding

    -- Campos de negocio
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(10, 2) NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Foreign Keys (sin constraint explícita)
    categoria_id BIGINT UNSIGNED NULL,  -- Apunta a categorias.pkid
    user_id BIGINT UNSIGNED NULL,       -- Apunta a users.id (autoincrement)

    -- Campos de auditoría (OBLIGATORIOS)
    created_by_id BIGINT UNSIGNED NULL, -- FK a users.id (quien creó)
    updated_by_id BIGINT UNSIGNED NULL, -- FK a users.id (quien actualizó)
    deleted_by_id BIGINT UNSIGNED NULL, -- FK a users.id (quien eliminó)

    -- Timestamps (OBLIGATORIOS)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL           -- Soft Deletes
);
```

**Características**:

- `pkid`: BIGINT auto-incremental (usada en FKs)
- `id`: UUID generado automáticamente por `BaseModel::boot()`
- Prefijo de tabla según cliente/proyecto
- **NO usar FOREIGN KEY constraints** (solo en comentarios/documentación)
- **SIEMPRE** incluir campos de auditoría
- **SIEMPRE** incluir `deleted_at` para SoftDeletes

---

### Campos de Auditoría (Obligatorios)

Todas las tablas de negocio DEBEN tener:

```php
// En la migración
$table->unsignedBigInteger('created_by_id')->nullable();  // FK a users.id
$table->unsignedBigInteger('updated_by_id')->nullable();  // FK a users.id
$table->unsignedBigInteger('deleted_by_id')->nullable();  // FK a users.id (NUEVO)
$table->timestamps();
$table->softDeletes();
```

#### Propósito:

- `created_by_id`: ID del usuario que creó el registro
    - Se obtiene con `auth()->id()` en `Service::crear()`
- `updated_by_id`: ID del usuario que actualizó por última vez
    - Se obtiene con `auth()->id()` en `Service::actualizar()`
- `deleted_by_id`: ID del usuario que eliminó (soft delete)
    - Se obtiene con `auth()->id()` en `Service::eliminar()`

#### Obtener Usuario Autenticado:

```php
// En cualquier Service
$userId = auth()->id();  // Retorna BIGINT de users.id (autoincrement)
```

---

### Prefijos de Tablas

Aunque **Peru_Lex** no usa prefijos consistentes, los proyectos futuros **DEBERÍAN** usar:

```
Formato: {cliente}_{modulo}_
Ejemplos:
  - epl_alumnos        (Escuela Peru Lex)
  - epl_cursos
  - epl_matriculas

  - sip_productos      (Sistema Inventario Perú)
  - sip_categorias

  - crm_clientes       (CRM genérico)
  - crm_contratos
```

**Ventajas**:

- Identificación rápida de a qué proyecto/módulo pertenece
- Evita colisiones en migraciones entre proyectos
- Facilita backups selectivos

---

### Comando de Migración (OBLIGATORIO)

**NUNCA crear migraciones manualmente**, siempre usar Artisan:

```bash
# Formato estándar
php artisan make:migration create_prefijo_nombre_tabla_table

# Ejemplos reales
php artisan make:migration create_epl_productos_table
php artisan make:migration create_sip_categorias_table
php artisan make:migration add_deleted_by_id_to_alumnos_table
```

**Resultado**: Laravel genera automáticamente el nombre con timestamp:

```
2026_04_06_153042_create_epl_productos_table.php
```

---

### Relaciones sin Foreign Keys

**Importante**: En este proyecto **NO se definen FOREIGN KEY constraints** en migraciones.

#### ¿Por qué?

- Evitar errores al eliminar registros relacionados
- SoftDeletes ya protege integridad referencial
- Mayor flexibilidad para operaciones bulk
- Simplifica rollbacks y migraciones

#### ¿Dónde se definen entonces?

Solo en los **Models** con Eloquent:

```php
// En Model Producto
public function categoria(): BelongsTo
{
    // FK apunta a categorias.pkid, no a categorias.id
    return $this->belongsTo(Categoria::class, 'categoria_id', 'pkid');
}

public function createdBy(): BelongsTo
{
    // FK apunta a users.id (BIGINT autoincrement)
    return $this->belongsTo(User::class, 'created_by_id', 'id');
}
```

---

### Nomenclatura de Migraciones

Laravel genera automáticamente con formato:

```
YYYY_MM_DD_HHMMSS_accion_nombre_tabla.php
```

Ejemplos:

- `2026_04_06_153042_create_productos_table.php`
- `2026_04_06_153512_add_deleted_by_id_to_alumnos_table.php`
- `2026_04_06_154002_alter_cursos_add_softdeletes.php`

---

## 5. Sistema de Permisos

### Tablas del Sistema de Permisos

#### 1. `content_model`

- **Propósito**: Define los modelos/tablas que tendrán permisos (tabla del sistema, se mapea desde frontend)
- **Campos**:
    - `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
    - `ap_label`: Prefijo del cliente (ej: "epl", "sl", "crm")
    - `ap_model`: Nombre del modelo (ej: "alumnos", "cursos") - **usado para generar permisos**
    - `ap_table`: Nombre de tabla (ej: "epl_alumnos", "sl_cursos")

**Nota**: Esta tabla NO tiene campos de auditoría ni timestamps porque es metadata del sistema manejada desde frontend.

#### 2. `permissions`

- **Propósito**: Define permisos específicos (CRUD + view_model)
- **Campos**:
    - `id`: BIGINT
    - `name`: Identificador único (ej: "can_view_alumnos")
    - `display_name`: Nombre legible
    - `content_model_id`: FK a `content_model.id` (BIGINT UNSIGNED)
    - `action`: Acción (view_model, create, update, delete, etc.)
    - `description`: Descripción del permiso
    - `is_active`: Boolean
    - `created_by_id`, `updated_by_id`: Auditoría

#### 3. `roles`

- **Propósito**: Define roles del sistema
- **Campos**:
    - `id`: BIGINT
    - `name`: Identificador único (ej: "admin", "profesor", "alumno")
    - `display_name`: Nombre legible
    - `description`: Descripción del rol
    - `is_active`: Boolean
    - `created_by_id`, `updated_by_id`: Auditoría

#### 4. `role_user` (pivot)

- **Propósito**: Asigna roles a usuarios
- **Campos**:
    - `role_id`: FK a roles
    - `user_id`: FK a users

#### 5. `permission_role` (pivot)

- **Propósito**: Asigna permisos a roles
- **Campos**:
    - `permission_id`: FK a permissions
    - `role_id`: FK a roles

#### 6. `user_permission` (pivot)

- **Propósito**: Permisos específicos de usuario (sobreescribe rol)
- **Campos**:
    - `user_id`: FK a users
    - `permission_id`: FK a permissions

### Nomenclatura de Permisos

- **Patrón**: `can_[action]_[ap_model]`
- **Generación**: Desde formulario frontend que mapea automáticamente 5 acciones base
- **Basadas en**: `ap_model` del `content_model`

**Acciones Estándar (mapeadas en frontend)**:

1. `can_view_model_{tabla}` → Puede ver formulario/menú (acceso a la vista)
2. `can_create_{tabla}` → Puede crear nuevos registros (método `store`)
3. `can_update_{tabla}` → Puede editar registros existentes (método `update`)
4. `can_delete_{tabla}` → Puede eliminar registros (método `destroy`)
5. `can_export_{tabla}` → Puede exportar datos (métodos `exportPdf`, `exportExcel`, etc.)

**Opcionalmente** (según necesidad del módulo):

- `can_restore_{tabla}` → Puede restaurar registros eliminados
- `can_view_deleted_{tabla}` → Puede ver registros eliminados (withTrashed)
- `can_bulk_{tabla}` → Puede hacer operaciones masivas

**Ejemplos Reales**:

```
content_model: ap_label = "epl", ap_model = "alumnos"
Genera:
  - can_view_model_alumnos
  - can_create_alumnos
  - can_update_alumnos
  - can_delete_alumnos
  - can_export_alumnos

content_model: ap_label = "sl", ap_model = "cursos"
Genera:
  - can_view_model_cursos
  - can_create_cursos
  - can_update_cursos
  - can_delete_cursos
  - can_export_cursos
```

**Correspondencia con CRUD**:

- `index` → Requiere `can_view_model_{tabla}`
- `show` → Requiere `can_view_model_{tabla}`
- `store` → Requiere `can_create_{tabla}`
- `update` → Requiere `can_update_{tabla}`
- `destroy` → Requiere `can_delete_{tabla}`
- `restore` → Requiere `can_restore_{tabla}`

### Generación de Permisos

**Proceso** (ejecutado desde frontend):

1. **Usuario crea Content Model** en formulario:

    ```json
    {
        "ap_label": "epl",
        "ap_model": "alumnos",
        "ap_table": "epl_alumnos"
    }
    ```

2. **Frontend lee `ap_model`** y genera automáticamente 5 permisos base:
    - Frontend tiene lógica que toma `ap_model` del content_model
    - Mapea automáticamente las 5 acciones estándar
    - Genera nombres: `can_[action]_[ap_model]`
    - Envía array de permisos al backend para persistir
3. **Backend persiste** en tabla `permissions`:

    ```php
    // content_model_id es el ID (BIGINT) del content_model creado
    [
      ['name' => 'can_view_model_alumnos', 'action' => 'view_model', 'content_model_id' => 1],
      ['name' => 'can_create_alumnos', 'action' => 'create', 'content_model_id' => 1],
      ['name' => 'can_update_alumnos', 'action' => 'update', 'content_model_id' => 1],
      ['name' => 'can_delete_alumnos', 'action' => 'delete', 'content_model_id' => 1],
      ['name' => 'can_export_alumnos', 'action' => 'export', 'content_model_id' => 1],
    ]
    ```

4. **Si se necesitan permisos adicionales**:
    - Se crea manualmente la lógica en backend (ej: `can_approve_alumnos`)
    - Se añade al formulario frontend para generación automática

**Nota Importante**: La tabla `content_model` NO tiene auditoría ni timestamps. Es metadata del sistema completamente manejada desde el frontend.

### Flujo de Permisos

1. **Content Model**: Se crea para cada tabla gestionada mediante formulario frontend
2. **Permissions**: Frontend lee `ap_model` y genera automáticamente los 5 permisos base
3. **Roles**: Admin asigna permisos a roles desde panel administrativo
4. **User Role**: Usuario recibe rol (tabla `role_user`)
5. **User Permission**: (Opcional) Permisos específicos individuales (tabla `user_permission`)

---

## 6. Convenciones de Código

### 6.1 Model

```php
<?php
namespace App\Models;

use App\Models\Traits\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NombreModelo extends BaseModel  // Usar BaseModel si tiene UUID
{
    use HasFilters, SoftDeletes;

    // Solo si difiere del default de Laravel
    protected $table = 'nombre_tabla';

    // Si usa UUID como PK
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',  // Si usa UUID
        'campo1',
        'campo2',
        // Auditoría
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',  // NUEVO
    ];

    protected $casts = [
        'fecha_campo' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Solo si usa UUID
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    // =========================================================================
    // Relaciones de Negocio
    // =========================================================================

    public function relacion(): BelongsTo
    {
        // Si relaciona con pkid en vez de id:
        return $this->belongsTo(OtroModelo::class, 'otro_id', 'pkid');
    }

    // =========================================================================
    // Relaciones de Auditoría (Opcional pero recomendado)
    // =========================================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id', 'id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id', 'id');
    }

    // =========================================================================
    // Scopes Globales (Evitar repetición)
    // =========================================================================

    // TODO: Mover a un Trait global reutilizable
    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactivos($query)
    {
        return $query->where('is_active', false);
    }
}
```

**Notas del Model**:

- ✅ **BaseModel** genera UUID automáticamente en el hook `boot()`
- ✅ **HasFilters** permite `->filter($filter)` en queries
- ✅ **SoftDeletes** permite `->delete()` sin eliminar físicamente
- ✅ **deleted_by_id** se añade al fillable
- ✅ Relaciones de auditoría son opcionales pero **muy recomendadas**
- 📌 **TODO**: Crear Trait global para scopes comunes (`Activos`, `Inactivos`, etc.)

````

### 6.2 Filter

```php
<?php
namespace App\Filters;

class NombreModeloFilter extends QueryFilter
{
    protected array $allowedFilters = [
        // Campos de negocio
        'campo_texto',
        'campo_exacto',
        'relacion_id',
        'is_active',

        // Filtros de auditoría estándar (TODOS los módulos)
        'created_at',
        'created_at_from',
        'created_at_to',
        'updated_at',
        'updated_at_from',
        'updated_at_to',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',  // Para queries withTrashed()
    ];

    // =========================================================================
    // Filtros de Negocio
    // =========================================================================

    public function campo_texto(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('campo_texto', 'like', "%{$value}%");
    }

    public function campo_exacto(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('campo_exacto', $value);
    }

    public function relacion_id(string $value): void
    {
        if ($value === '') return;
        // Para filtros sobre relaciones
        $this->builder->whereHas('relacion', fn($q) => $q->where('id', $value));
    }

    public function is_active(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('is_active', (bool) $value);
    }

    // =========================================================================
    // Filtros de Auditoría Estándar
    // TODO: Mover a Trait global para evitar repetición
    // =========================================================================

    public function created_at(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('created_at', $value);
    }

    public function created_at_from(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('created_at', '>=', $value);
    }

    public function created_at_to(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('created_at', '<=', $value);
    }

    public function updated_at(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('updated_at', $value);
    }

    public function updated_at_from(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('updated_at', '>=', $value);
    }

    public function updated_at_to(string $value): void
    {
        if ($value === '') return;
        $this->builder->whereDate('updated_at', '<=', $value);
    }

    public function created_by_id(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('created_by_id', $value);
    }

    public function updated_by_id(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('updated_by_id', $value);
    }

    public function deleted_by_id(string $value): void
    {
        if ($value === '') return;
        $this->builder->where('deleted_by_id', $value);
    }
}
```

**Notas del Filter**:
- ✅ Early return si `$value === ''` **siempre**
- ✅ Filtros estándar de auditoría en **TODOS** los módulos
- ✅ Rango de fechas con `_from` y `_to`
- ✅ Búsquedas parciales: `like "%{$value}%"`
- ✅ Búsquedas exactas: `where('campo', $value)`
- ✅ Filtros por relaciones: `whereHas()`
- 📌 **TODO**: Crear Trait global con filtros de auditoría para evitar duplicación
````

### 6.3 Requests

#### BaseRequest

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseNombreModeloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'campo.required' => 'El campo es obligatorio.',
            'campo.max' => 'El campo no puede superar X caracteres.',
            'campo.unique' => 'El campo ya está registrado.',
        ];
    }
}
```

#### StoreRequest

```php
<?php
namespace App\Http\Requests;

class StoreNombreModeloRequest extends BaseNombreModeloRequest
{
    public function rules(): array
    {
        return [
            'campo_obligatorio' => ['required', 'string', 'max:255'],
            'campo_unico' => ['required', 'string', 'unique:tabla,campo'],
            'campo_opcional' => ['nullable', 'string'],
            'fk_campo' => ['required', 'integer', 'exists:otra_tabla,id'],
        ];
    }
}
```

#### UpdateRequest

```php
<?php
namespace App\Http\Requests;

class UpdateNombreModeloRequest extends BaseNombreModeloRequest
{
    public function rules(): array
    {
        $id = $this->route('nombreModelo')?->id;

        return [
            'campo_obligatorio' => ['sometimes', 'required', 'string', 'max:255'],
            'campo_unico' => ['sometimes', 'required', "unique:tabla,campo,{$id}"],
            'campo_opcional' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

### 6.4 Resource

```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NombreModeloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campo1' => $this->campo1,
            'campo2' => $this->campo2,
            // Relaciones solo cuando están cargadas
            'relacion' => new OtroResource($this->whenLoaded('relacion')),
            'coleccion' => OtroResource::collection($this->whenLoaded('coleccion')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
```

### 6.5 Service

```php
<?php
namespace App\Services;

use App\Models\NombreModelo;
use App\Traits\ResolvesUuidToPkid;
use Illuminate\Support\Facades\DB;

class NombreModeloService
{
    use ResolvesUuidToPkid;

    /**
     * Crear un nuevo registro
     */
    public function crear(array $data): NombreModelo
    {
        // Resolver UUIDs a PKIDs si es necesario
        $data = $this->resolveIds($data, [
            'content_model_id' => 'content_model',
            'otra_relacion_id' => 'otra_tabla',
        ]);

        // Auditoría: quien creó y actualizó
        $data['created_by_id'] = auth()->id();
        $data['updated_by_id'] = auth()->id();

        return NombreModelo::create($data);
    }

    /**
     * Actualizar un registro existente
     */
    public function actualizar(NombreModelo $modelo, array $data): NombreModelo
    {
        // Resolver UUIDs a PKIDs si es necesario
        $data = $this->resolveIds($data, [
            'content_model_id' => 'content_model',
        ]);

        // Auditoría: quien actualizó
        $data['updated_by_id'] = auth()->id();

        $modelo->update($data);
        return $modelo->refresh();
    }

    /**
     * Eliminar (soft delete) un registro
     * IMPORTANTE: Registra quien eliminó antes de hacer el soft delete
     */
    public function eliminar(NombreModelo $modelo): NombreModelo
    {
        // Auditoría: registrar quien eliminó ANTES del soft delete
        $modelo->update(['deleted_by_id' => auth()->id()]);

        // Ahora hacer el soft delete
        $modelo->delete();

        return $modelo;
    }

    /**
     * Restaurar un registro eliminado (soft deleted)
     * OPCIONAL: Limpiar deleted_by_id al restaurar
     */
    public function restaurar(string $id): NombreModelo
    {
        $modelo = NombreModelo::withTrashed()->where('id', $id)->firstOrFail();

        // Opción 1: Limpiar deleted_by_id al restaurar
        $modelo->update(['deleted_by_id' => null]);
        $modelo->restore();

        // Opción 2: Mantener deleted_by_id para historial
        // $modelo->restore();

        return $modelo;
    }
}
```

**Notas Importantes del Service**:

- ✅ Todo método que modifica datos actualiza campos de auditoría
- ✅ `auth()->id()` retorna BIGINT (users.id es autoincrement)
- ✅ `deleted_by_id` se registra **ANTES** del `delete()`
- ✅ `resolveIds()` convierte UUIDs a PKIDs para FKs
- ✅ Siempre retornar el modelo para chainability
- ✅ Usar `refresh()` después de `update()` para obtener estado actual de BD

````

### 6.6 Controller

```php
<?php
namespace App\Http\Controllers\Api;

use App\Filters\NombreModeloFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNombreModeloRequest;
use App\Http\Requests\UpdateNombreModeloRequest;
use App\Http\Resources\NombreModeloResource;
use App\Http\Traits\ApiResponse;
use App\Models\NombreModelo;
use App\Services\NombreModeloService;

/**
 * @OA\Tag(name="NombreModelo", description="Gestión de...")
 */
class NombreModeloController extends Controller
{
    use ApiResponse;

    public function __construct(protected NombreModeloService $service) {}

    /**
     * @OA\Get(
     *     path="/api/nombre-modelos",
     *     tags={"NombreModelo"},
     *     summary="Listar registros",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lista de registros")
     * )
     */
    public function index(NombreModeloFilter $filter)
    {
        $data = NombreModelo::filter($filter)->with('relacion')->get();
        return $this->responseSuccess(
            'Registros obtenidos correctamente',
            NombreModeloResource::collection($data)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/nombre-modelos/{nombreModelo}",
     *     tags={"NombreModelo"},
     *     summary="Obtener un registro",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="nombreModelo", in="path", required=true),
     *     @OA\Response(response=200, description="Detalle del registro")
     * )
     */
    public function show(NombreModelo $nombreModelo)
    {
        $nombreModelo->load('relacion');
        return $this->responseSuccess(
            'Registro obtenido correctamente',
            new NombreModeloResource($nombreModelo)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/nombre-modelos",
     *     tags={"NombreModelo"},
     *     summary="Crear registro",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Registro creado")
     * )
     */
    public function store(StoreNombreModeloRequest $request)
    {
        $modelo = $this->service->crear($request->validated());
        $modelo->load('relacion');
        return $this->responseSuccess(
            'Registro creado correctamente',
            new NombreModeloResource($modelo)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/nombre-modelos/{nombreModelo}",
     *     tags={"NombreModelo"},
     *     summary="Actualizar registro",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Registro actualizado")
     * )
     */
    public function update(UpdateNombreModeloRequest $request, NombreModelo $nombreModelo)
    {
        $modelo = $this->service->actualizar($nombreModelo, $request->validated());
        $modelo->load('relacion');
        return $this->responseSuccess(
            'Registro actualizado correctamente',
            new NombreModeloResource($modelo)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/nombre-modelos/{nombreModelo}",
     *     tags={"NombreModelo"},
     *     summary="Eliminar registro",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Registro eliminado")
     * )
     */
    public function destroy(NombreModelo $nombreModelo)
    {
        $deleted = $this->service->eliminar($nombreModelo);
        return $this->responseSuccess(
            'Registro eliminado correctamente',
            new NombreModeloResource($deleted)
        );
    }

    // Solo si usa SoftDeletes
    public function restore(string $id)
    {
        $modelo = $this->service->restaurar($id);
        return $this->responseSuccess(
            'Registro restaurado correctamente',
            new NombreModeloResource($modelo)
        );
    }
}
````

---

## 7. Sistema de Rutas

### Ubicación y Carga

- **Archivo principal**: `routes/api.php`
- **Archivos modulares**: `routes/modules/*.php`
- **Carga**: Se hace mediante `require` en `api.php`

### Patrón Estándar de Ruta

```php
<?php
use App\Http\Controllers\Api\NombreModeloController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Rutas extra (siempre ANTES de apiResource)
    Route::post('/nombre-modelos/{id}/restore', [NombreModeloController::class, 'restore']);
    Route::post('/nombre-modelos/bulk', [NombreModeloController::class, 'bulkStore']);
    Route::get('/nombre-modelos/export/pdf', [NombreModeloController::class, 'exportPdf']);

    // CRUD estándar (SIEMPRE usar apiResource)
    Route::apiResource('nombre-modelos', NombreModeloController::class);
});
```

### Rutas Generadas por apiResource

```
GET    /api/nombre-modelos           → index
GET    /api/nombre-modelos/{id}      → show
POST   /api/nombre-modelos           → store
PUT    /api/nombre-modelos/{id}      → update
PATCH  /api/nombre-modelos/{id}      → update
DELETE /api/nombre-modelos/{id}      → destroy
```

### Middleware

- **Producción**: `auth:sanctum` (autenticación con tokens)
- **Desarrollo**: Puede usar `ApiToken` (si se implementa)

---

## 8. Helpers y Traits

### 8.1 ApiResponse (Trait)

**Ubicación**: `app/Http/Traits/ApiResponse.php`

```php
<?php
namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function responseSuccess(
        string $message,
        mixed $data = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function responseError(
        string $message,
        mixed $errors = null,
        int $status = 422
    ): JsonResponse {
        $body = ['status' => 'error', 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        return response()->json($body, $status);
    }

    protected function responseNotFound(
        string $message = 'Registro no encontrado'
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 404);
    }
}
```

**Formato de Respuesta Estándar**:

```json
{
    "status": "success",
    "message": "Mensaje descriptivo",
    "data": [...]
}
```

### 8.2 ResolvesUuidToPkid (Trait)

**Ubicación**: `app/Traits/ResolvesUuidToPkid.php`

```php
<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait ResolvesUuidToPkid
{
    protected function resolveId(mixed $value, string $table): mixed
    {
        if (is_null($value) || is_numeric($value)) {
            return $value;
        }
        return DB::table($table)->where('id', $value)->value('pkid') ?? $value;
    }

    protected function resolveIds(array $data, array $fields): array
    {
        foreach ($fields as $field => $table) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->resolveId($data[$field], $table);
            }
        }
        return $data;
    }
}
```

**Uso en Service**:

```php
use App\Traits\ResolvesUuidToPkid;

class MiService
{
    use ResolvesUuidToPkid;

    public function crear(array $data): Model
    {
        $data = $this->resolveIds($data, [
            'content_model_id' => 'content_model',
            'user_id' => 'users',
        ]);

        return Model::create($data);
    }
}
```

### 8.3 HasFilters (Trait)

**Ubicación**: `app/Models/Traits/HasFilters.php`

```php
<?php
namespace App\Models\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

trait HasFilters
{
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
```

**Uso en Controller**:

```php
public function index(NombreModeloFilter $filter)
{
    $data = NombreModelo::filter($filter)->get();
    return $this->responseSuccess('...', $data);
}
```

### 8.4 QueryFilter (Clase Base)

**Ubicación**: `app/Filters/QueryFilter.php`

```php
<?php
namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    protected Builder $builder;
    protected array $allowedFilters = [];

    public function __construct(protected Request $request) {}

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $key => $value) {
            if (in_array($key, $this->allowedFilters) && method_exists($this, $key)) {
                $this->$key($value);
            }
        }

        return $this->builder;
    }
}
```

---

## 9. Frontend Integration

### 9.1 Service Pattern en Angular

```typescript
// nombre-modelo.service.ts
export interface INombreModelo {
    id?: string;
    campo1: string;
    campo2: string;
    created_at?: string;
}

@Injectable({
    providedIn: "root",
})
export class NombreModeloService {
    private apiUrl = `${environment.apiUrl}/nombre-modelos`;

    constructor(private http: HttpClient) {}

    // Listar con filtros dinámicos
    index(filters?: any): Observable<{ data: INombreModelo[] }> {
        let params = new HttpParams();

        // Construcción dinámica de filtros
        if (filters) {
            Object.keys(filters).forEach((key) => {
                if (filters[key] !== null && filters[key] !== undefined) {
                    params = params.set(key, filters[key]);
                }
            });
        }

        return this.http.get<{ data: INombreModelo[] }>(this.apiUrl, {
            params,
        });
    }

    // Obtener uno
    show(id: string): Observable<{ data: INombreModelo }> {
        return this.http.get<{ data: INombreModelo }>(`${this.apiUrl}/${id}`);
    }

    // Crear
    store(data: INombreModelo): Observable<{ data: INombreModelo }> {
        return this.http.post<{ data: INombreModelo }>(this.apiUrl, data);
    }

    // Actualizar
    update(
        id: string,
        data: Partial<INombreModelo>,
    ): Observable<{ data: INombreModelo }> {
        return this.http.put<{ data: INombreModelo }>(
            `${this.apiUrl}/${id}`,
            data,
        );
    }

    // Eliminar
    destroy(id: string): Observable<{ data: INombreModelo }> {
        return this.http.delete<{ data: INombreModelo }>(
            `${this.apiUrl}/${id}`,
        );
    }

    // Restaurar (si usa SoftDeletes)
    restore(id: string): Observable<{ data: INombreModelo }> {
        return this.http.post<{ data: INombreModelo }>(
            `${this.apiUrl}/${id}/restore`,
            {},
        );
    }
}
```

### 9.2 Uso en Componentes

```typescript
export class NombreModeloComponent implements OnInit {
    models: INombreModelo[] = [];

    constructor(
        private nombreModeloService: NombreModeloService,
        private messageService: MessageService,
    ) {}

    loadData() {
        // Construcción dinámica de filtros
        const filters: any = {};

        if (this.tiendaSeleccionada) {
            filters.tienda_id = removeHyphensFromUUID(this.tiendaSeleccionada);
        }

        if (this.periodoSeleccionado) {
            filters.periodo_id = this.periodoSeleccionado;
        }

        this.nombreModeloService.index(filters).subscribe({
            next: (response) => {
                this.models = response.data;
                // Procesar datos...
            },
            error: (error) => {
                const is404 =
                    error.status === 404 ||
                    error.error?.errors?.[0]?.status === 404;
                if (is404) {
                    this.initEmptyTable();
                } else {
                    this.messageError(
                        "Error al cargar: " +
                            (error.error?.message || error.message),
                    );
                }
            },
        });
    }
}
```

### 9.3 Sistema de Permisos en Frontend

```typescript
// permiso.service.ts
@Injectable({
    providedIn: "root",
})
export class PermisoService {
    private grupos$ = new BehaviorSubject<string[]>([]);
    private permisos$ = new BehaviorSubject<string[]>([]);

    loadPermisos(userId: string) {
        this.http
            .get<{
                grupos: string[];
                permisos: string[];
            }>(`/api/permisos/${userId}`)
            .subscribe((data) => {
                this.grupos$.next(data.grupos);
                this.permisos$.next(data.permisos);
            });
    }

    hasPermission(permiso: string): boolean {
        return this.permisos$.value.includes(permiso);
    }

    hasRole(rol: string): boolean {
        return this.grupos$.value.includes(rol);
    }

    get isAdmin(): boolean {
        return this.hasRole("admin");
    }

    canViewModel(tabla: string): boolean {
        return this.hasPermission(`can_view_model_${tabla}`);
    }

    canCreate(tabla: string): boolean {
        return this.hasPermission(`can_create_${tabla}`);
    }

    canUpdate(tabla: string): boolean {
        return this.hasPermission(`can_update_${tabla}`);
    }

    canDelete(tabla: string): boolean {
        return this.hasPermission(`can_delete_${tabla}`);
    }
}
```

**Uso en Guards**:

```typescript
@Injectable()
export class PermisoGuard implements CanActivate {
    constructor(private permisoService: PermisoService) {}

    canActivate(route: ActivatedRouteSnapshot): boolean {
        const requiredPermission = route.data["permission"];
        return this.permisoService.hasPermission(requiredPermission);
    }
}
```

**Uso en Rutas**:

```typescript
const routes: Routes = [
    {
        path: "alumnos",
        component: AlumnosComponent,
        canActivate: [PermisoGuard],
        data: { permission: "can_view_model_alumnos" },
    },
];
```

---

## 10. Seeders para web_template_back

### Objetivo

Proveer al template base (`web_template_back`) con seeders pre-configurados para inicializar:

- Roles básicos del sistema
- Permisos iniciales
- Usuario administrador
- Content Types base (si aplica)

### Seeders Obligatorios

#### 1. RolesAndPermissionsSeeder.php

```php
<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Crear roles base
        $admin  = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrador', 'is_active' => true]
        );

        $editor = Role::firstOrCreate(
            ['name' => 'editor'],
            ['display_name' => 'Editor', 'is_active' => true]
        );

        $viewer = Role::firstOrCreate(
            ['name' => 'viewer'],
            ['display_name' => 'Visualizador', 'is_active' => true]
        );

        // Permisos base del sistema
        $systemModules = [
            'users'       => ['create', 'read', 'update', 'delete'],
            'roles'       => ['create', 'read', 'update', 'delete'],
            'permissions' => ['create', 'read', 'update', 'delete'],
        ];

        $allPermissionIds = [];
        foreach ($systemModules as $module => $actions) {
            foreach ($actions as $action) {
                $perm = Permission::firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'display_name' => ucfirst($action) . ' ' . ucfirst($module),
                        'action' => $action,
                        'is_active' => true
                    ]
                );
                $allPermissionIds[] = $perm->id;
            }
        }

        // Asignar permisos a roles
        $admin->permissions()->sync($allPermissionIds);  // Admin tiene todos
        $editor->permissions()->sync(
            Permission::whereIn('action', ['read', 'update'])->pluck('id')
        );
        $viewer->permissions()->sync(
            Permission::where('action', 'read')->pluck('id')
        );

        $this->command->info('✓ Roles y permisos base creados');
    }
}
```

#### 2. AdminUserSeeder.php

```php
<?php
namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario administrador por defecto
        $admin = User::firstOrCreate(
            ['email' => 'admin@template.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole) {
            // Asignar rol en tabla pivot
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);

            // Actualizar campo rol_id en users
            $admin->update(['rol_id' => $adminRole->id]);
        }

        $this->command->info('✓ Admin creado: admin@template.com / password');
    }
}
```

**Nota**: Este seeder puede personalizarse por proyecto. El email y nombre se cambiarán según cliente.

#### 3. DatabaseSeeder.php (Orquestador)

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            // ContentTypesSeeder::class,  // Opcional según proyecto
        ]);
    }
}
```

### Seeders Opcionales

#### ContentModelsSeeder.php (Si el proyecto usa sistema de permisos)

```php
<?php
namespace Database\Seeders;

use App\Models\ContentModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentModelsSeeder extends Seeder
{
    public function run(): void
    {
        $contentModels = [
            ['ap_label' => 'Usuarios', 'ap_model' => 'users', 'ap_table' => 'users'],
            ['ap_label' => 'Roles', 'ap_model' => 'roles', 'ap_table' => 'roles'],
            ['ap_label' => 'Permisos', 'ap_model' => 'permissions', 'ap_table' => 'permissions'],
        ];

        foreach ($contentModels as $cm) {
            ContentModel::firstOrCreate(
                ['ap_table' => $cm['ap_table']],
                [
                    'ap_label' => $cm['ap_label'],
                    'ap_model' => $cm['ap_model'],
                ]
            );
        }

        $this->command->info('✓ Content Models base creados');
    }
}
```

### Ejecución de Seeders

```bash
# Ejecutar todos los seeders
php artisan db:seed

# Ejecutar un seeder específico
php artisan db:seed --class=RolesAndPermissionsSeeder

# Fresh migration + seeders (CUIDADO: elimina datos)
php artisan migrate:fresh --seed
```

### Personalización para Proyectos

Cuando se clone `web_template_back` para un nuevo proyecto:

1. **Modificar AdminUserSeeder**:

    ```php
    $admin = User::firstOrCreate(
        ['email' => 'admin@clienteX.com'],  // ← Cambiar
        [
            'name' => 'Admin ClienteX',      // ← Cambiar
            'password' => Hash::make('temporal123'),  // ← Cambiar
            'is_active' => true
        ]
    );
    ```

2. **Añadir roles específicos** al proyecto en `RolesAndPermissionsSeeder`
3. **Crear ContentTypes** según los módulos del proyecto
4. **Ejecutar** después de clonar:
    ```bash
    php artisan migrate:fresh --seed
    ```

### Referencia: Seeder de Gabriel (Peru_Lex)

**Ubicación**: `database/seeders/GabrielUserSeeder.php`

Este seeder es **específico del proyecto Peru_Lex** y NO debe estar en `web_template_back`.

Para `web_template_back`, usar solo seeders genéricos que puedan reutilizarse en cualquier proyecto.

---

## 11. Automatización Propuesta

### 10.1 Objetivo

Crear scripts Python que lean la estructura de BD y generen automáticamente:

1. Migraciones
2. Models
3. Filters
4. Requests (Base, Store, Update)
5. Resources
6. Services
7. Controllers
8. Routes
9. Interfaces TypeScript (Frontend)
10. Services Angular (Frontend)

**Nota Importante**: El script automatizado es para **tablas de negocio** únicamente. Las tablas del sistema (`users`, `roles`, `permissions`, `content_model`) se crean manualmente y están incluidas en `web_template_back`.

### 10.2 Entrada del Script

#### Opción A: Estructura JSON

```json
{
    "tableName": "productos",
    "usesUuid": true,
    "usesSoftDeletes": true,
    "fields": [
        {
            "name": "nombre",
            "type": "string",
            "length": 255,
            "nullable": false,
            "unique": false
        },
        {
            "name": "descripcion",
            "type": "text",
            "nullable": true
        },
        {
            "name": "precio",
            "type": "decimal",
            "precision": 10,
            "scale": 2,
            "nullable": false
        },
        {
            "name": "categoria_id",
            "type": "foreignKey",
            "references": {
                "table": "categorias",
                "column": "pkid"
            },
            "nullable": false
        }
    ],
    "relations": [
        {
            "name": "categoria",
            "type": "belongsTo",
            "model": "Categoria",
            "foreignKey": "categoria_id",
            "ownerKey": "pkid"
        }
    ],
    "scopes": [
        {
            "name": "activos",
            "condition": "is_active = true"
        }
    ],
    "permissions": {
        "label": "Productos",
        "actions": ["view_model", "create", "update", "delete", "restore"]
    }
}
```

#### Opción B: Introspección de BD

```python
import pymysql

def introspect_table(table_name):
    connection = pymysql.connect(
        host='localhost',
        user='root',
        password='',
        database='peru_lex_db'
    )

    cursor = connection.cursor()
    cursor.execute(f"DESCRIBE {table_name}")

    columns = cursor.fetchall()
    structure = {
        'tableName': table_name,
        'fields': []
    }

    for col in columns:
        field = {
            'name': col[0],
            'type': col[1],
            'nullable': col[2] == 'YES',
            'key': col[3],
            'default': col[4],
            'extra': col[5]
        }
        structure['fields'].append(field)

    return structure
```

### 10.3 Estructura del Script Python

```python
# generator.py
from jinja2 import Template
import os
import json

class LaravelModuleGenerator:
    def __init__(self, config):
        self.config = config
        self.templates_dir = 'templates/'

    def generate_migration(self):
        template = self.load_template('migration.jinja2')
        return template.render(self.config)

    def generate_model(self):
        template = self.load_template('model.jinja2')
        return template.render(self.config)

    def generate_filter(self):
        template = self.load_template('filter.jinja2')
        return template.render(self.config)

    def generate_requests(self):
        base = self.load_template('base_request.jinja2').render(self.config)
        store = self.load_template('store_request.jinja2').render(self.config)
        update = self.load_template('update_request.jinja2').render(self.config)
        return {'base': base, 'store': store, 'update': update}

    def generate_resource(self):
        template = self.load_template('resource.jinja2')
        return template.render(self.config)

    def generate_service(self):
        template = self.load_template('service.jinja2')
        return template.render(self.config)

    def generate_controller(self):
        template = self.load_template('controller.jinja2')
        return template.render(self.config)

    def generate_route(self):
        template = self.load_template('route.jinja2')
        return template.render(self.config)

    def generate_typescript_interface(self):
        template = self.load_template('typescript_interface.jinja2')
        return template.render(self.config)

    def generate_angular_service(self):
        template = self.load_template('angular_service.jinja2')
        return template.render(self.config)

    def load_template(self, filename):
        with open(os.path.join(self.templates_dir, filename), 'r') as f:
            return Template(f.read())

    def write_file(self, path, content):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as f:
            f.write(content)

    def generate_all(self):
        # Backend
        migration = self.generate_migration()
        model = self.generate_model()
        filter_code = self.generate_filter()
        requests = self.generate_requests()
        resource = self.generate_resource()
        service = self.generate_service()
        controller = self.generate_controller()
        route = self.generate_route()

        # Frontend
        ts_interface = self.generate_typescript_interface()
        ng_service = self.generate_angular_service()

        # Escribir archivos
        base_name = self.config['tableName']
        model_name = self.to_pascal_case(base_name)

        self.write_file(f'database/migrations/create_{base_name}_table.php', migration)
        self.write_file(f'app/Models/{model_name}.php', model)
        self.write_file(f'app/Filters/{model_name}Filter.php', filter_code)
        self.write_file(f'app/Http/Requests/Base{model_name}Request.php', requests['base'])
        self.write_file(f'app/Http/Requests/Store{model_name}Request.php', requests['store'])
        self.write_file(f'app/Http/Requests/Update{model_name}Request.php', requests['update'])
        self.write_file(f'app/Http/Resources/{model_name}Resource.php', resource)
        self.write_file(f'app/Services/{model_name}Service.php', service)
        self.write_file(f'app/Http/Controllers/Api/{model_name}Controller.php', controller)
        self.write_file(f'routes/modules/{base_name}.php', route)

        # Frontend
        self.write_file(f'frontend/interfaces/{base_name}.interface.ts', ts_interface)
        self.write_file(f'frontend/services/{base_name}.service.ts', ng_service)

        print(f"✓ Módulo {model_name} generado completamente")

    @staticmethod
    def to_pascal_case(snake_str):
        return ''.join(word.capitalize() for word in snake_str.split('_'))

# Uso
if __name__ == '__main__':
    with open('config/producto.json', 'r') as f:
        config = json.load(f)

    generator = LaravelModuleGenerator(config)
    generator.generate_all()
```

### 10.4 Templates Jinja2

#### migration.jinja2

```jinja2
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{{ tableName }}', function (Blueprint $table) {
            {% if usesUuid %}
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();
            {% else %}
            $table->id();
            {% endif %}

            {% for field in fields %}
            {% if field.type == 'string' %}
            $table->string('{{ field.name }}', {{ field.length }}){% if field.nullable %}.nullable(){% endif %}{% if field.unique %}.unique(){% endif %};
            {% elif field.type == 'text' %}
            $table->text('{{ field.name }}'){% if field.nullable %}.nullable(){% endif %};
            {% elif field.type == 'integer' %}
            $table->integer('{{ field.name }}'){% if field.nullable %}.nullable(){% endif %};
            {% elif field.type == 'decimal' %}
            $table->decimal('{{ field.name }}', {{ field.precision }}, {{ field.scale }}){% if field.nullable %}.nullable(){% endif %};
            {% elif field.type == 'boolean' %}
            $table->boolean('{{ field.name }}'){% if not field.nullable %}.default(false){% else %}.nullable(){% endif %};
            {% elif field.type == 'date' %}
            $table->date('{{ field.name }}'){% if field.nullable %}.nullable(){% endif %};
            {% elif field.type == 'datetime' %}
            $table->datetime('{{ field.name }}'){% if field.nullable %}.nullable(){% endif %};
            {% elif field.type == 'foreignKey' %}
            $table->unsignedBigInteger('{{ field.name }}'){% if field.nullable %}.nullable(){% endif %};
            $table->foreign('{{ field.name }}')->references('{{ field.references.column }}')->on('{{ field.references.table }}');
            {% endif %}
            {% endfor %}

            {% if usesUuid %}
            $table->unsignedBigInteger('created_by_id')->nullable();  // FK a users.id
            $table->unsignedBigInteger('updated_by_id')->nullable();  // FK a users.id
            $table->unsignedBigInteger('deleted_by_id')->nullable();  // FK a users.id
            {% endif %}

            $table->timestamps();
            {% if usesSoftDeletes %}
            $table->softDeletes();
            {% endif %}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{{ tableName }}');
    }
};
```

### 10.5 CLI Interface

```bash
# Generar módulo completo desde JSON
python generator.py generate --config config/producto.json

# Generar desde introspección de BD
python generator.py generate --table productos --introspect

# Generar solo backend
python generator.py generate --config config/producto.json --backend-only

# Generar solo frontend
python generator.py generate --config config/producto.json --frontend-only

# Generar permisos para una tabla existente
python generator.py permissions --table productos

# Listar tablas disponibles para introspección
python generator.py list-tables
```

---

## 11. Checklist de Configuración Pendiente

### Backend

- [ ] Configurar middleware `ApiToken` (actualmente usa `auth:sanctum`)
- [ ] Implementar validación de permisos en controllers
- [ ] Crear seeders para permisos estándar
- [ ] Documentar scopes globales personalizados
- [ ] Implementar logs de auditoría
- [ ] Configurar rate limiting por endpoint

### Frontend

- [ ] Parametrizar construcción dinámica de filtros
- [ ] Implementar interceptores para manejo de errores
- [ ] Crear guards de permisos reutilizables
- [ ] Documentar componentes de formularios reutilizables
- [ ] Implementar caché de respuestas
- [ ] Crear directivas de permisos (`*hasPermission`)

### Automatización

- [ ] Crear templates Jinja2 para todos los componentes
- [ ] Implementar validación de JSON de configuración
- [ ] Añadir soporte para relaciones polimórficas
- [ ] Implementar generación de tests unitarios
- [ ] Crear script de rollback de generación
- [ ] Documentar convenciones de nombres

---

## 12. Preguntas para Refinamiento

### Sobre Base de Datos

1. ¿Todas las tablas nuevas usarán UUID como PK principal?
2. ¿Todas las tablas tendrán campos de auditoría (`created_by_id`, `updated_by_id`)?
3. ¿Todas las tablas usarán `SoftDeletes` por defecto?
4. ¿Hay algún prefijo estándar para las tablas (ej: `epl_`)?

### Sobre Permisos

1. ¿Los permisos se generarán automáticamente al crear un `content_model`?
2. ¿Habrá permisos adicionales más allá del CRUD estándar?
3. ¿Se validarán permisos en el backend (middleware) o solo en frontend?
4. ¿Los permisos se asignarán por defecto a algún rol específico?

### Sobre Rutas

1. ¿El middleware será siempre `auth:sanctum` o se usará `ApiToken` personalizado?
2. ¿Habrá rate limiting diferenciado por tipo de endpoint?
3. ¿Se requieren rutas públicas (sin autenticación)?

### Sobre Filtros

1. ¿Habrá filtros comunes a todas las entidades (ej: `created_at_from`, `created_at_to`)?
2. ¿Se implementarán filtros por relaciones anidadas?
3. ¿Se requiere paginación en todos los `index`?

### Sobre Validaciones

1. ¿Habrá validaciones personalizadas comunes (ej: RUC, DNI)?
2. ¿Las validaciones de unicidad verificarán registros eliminados (`withTrashed`)?
3. ¿Se requieren validaciones asíncronas (ej: verificar existencia en API externa)?

### Sobre el Frontend

1. ¿Se usará un helper para transformar UUIDs (ej: `removeHyphensFromUUID`)?
2. ¿Los servicios Angular tendrán caché de respuestas?
3. ¿Se implementarán formularios reactivos o template-driven?
4. ¿Habrá componentes reutilizables para listar/crear/editar?

### Sobre la Automatización

1. ¿La entrada será JSON manual o introspección de BD?
2. ¿Se generarán también tests unitarios?
3. ¿Se integrará con Git (commit automático)?
4. ¿Se ejecutará la migración automáticamente?
5. ¿Se generará documentación Swagger automáticamente?

---

## 13. Comandos Útiles

### Generación Manual

```bash
# Crear migración
php artisan make:migration create_productos_table

# Crear modelo
php artisan make:model Producto

# Crear controller
php artisan make:controller Api/ProductoController --api

# Crear request
php artisan make:request StoreProductoRequest

# Crear resource
php artisan make:resource ProductoResource

# Ejecutar migraciones
php artisan migrate

# Generar documentación Swagger
php artisan l5-swagger:generate
```

### Desarrollo

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ver rutas
php artisan route:list

# Debugging
php artisan tinker
```

---

## 14. Recursos Adicionales

### Documentación

- Laravel 12: https://laravel.com/docs/12.x
- Sanctum: https://laravel.com/docs/12.x/sanctum
- Swagger: https://github.com/DarkaOnLine/L5-Swagger

### Archivos de Referencia en el Proyecto

- `COMPONENT_GUIDE.md`: Guía de componentes
- `README.md`: Documentación principal
- `PORTS.md`: Configuración de puertos
- `EMAILS_CONFIGURACION.md`: Configuración de emails

---

## 15. Resumen Ejecutivo y Plan de Acción

### ✅ Decisiones Arquitectónicas Confirmadas

#### Base de Datos

- ✅ **NO todas** las tablas usan UUID+PKID
    - Tablas del sistema (`users`, `roles`, `permissions`, `content_model`): Solo ID autoincremental
    - Tablas de negocio: UUID + PKID dual key system
    - `ResolvesUuidToPkid` debe soportar ambos casos (ya lo hace)

- ✅ **Auditoría completa** en tablas de negocio y algunas del sistema:
    - `created_by_id BIGINT UNSIGNED` → Usuario que creó (FK a users.id)
    - `updated_by_id BIGINT UNSIGNED` → Usuario que actualizó (FK a users.id)
    - `deleted_by_id BIGINT UNSIGNED` → **NUEVO**: Usuario que eliminó (FK a users.id)
    - Todos apuntan a `users.id` (autoincrement BIGINT)
    - Obtener con: `auth()->id()`
    - **Excepción**: `content_model` NO tiene auditoría (es metadata manejada desde frontend)

- ✅ **SoftDeletes** obligatorio en tablas de negocio (no en `content_model`)
- ✅ **Prefijos de tabla**: Aunque Peru_Lex no los usa, futuros proyectos DEBERÍAN usarlos
- ✅ **NO usar FOREIGN KEY** en migraciones (solo en modelos)

#### Permisos

- ✅ Se crean desde **formulario frontend**
- ✅ Frontend **lee `ap_model`** del `content_model` y mapea automáticamente **5 acciones base**: `view_model`, `create`, `update`, `delete`, `export`
- ✅ Genera nombres: `can_create_[ap_model]` (ej: `can_create_alumnos`)
- ✅ Permisos adicionales se crean manualmente según necesidad
- ✅ `content_model` almacena metadata (ap_label, ap_model, ap_table) **sin auditoría**

#### Autenticación

- ✅ **Laravel Sanctum** (recomendado para el proyecto)
- ✅ Tokens expire en 30 minutos
- ✅ Middleware: `auth:sanctum`
- 📌 **Futuro**: Considerar ApiToken en contenedor separado (cuando haya presupuesto)

#### CI/CD

- ✅ Deploy con **GitHub Actions** a rama `main`
- ✅ **NUNCA** trabajar directamente en `main` → usar `dev` u otras ramas
- ✅ **Swagger** se regenera automáticamente en deploy
- ✅ Runner auto-alojado en servidor

---

### 📋 Plan de Acción: web_template_back

#### Fase 1: Preparar Base Template

1. ✅ Clonar o crear repo `web_template_back`
2. ✅ Incluir migraciones base:
    - `users` (ID autoincrement BIGINT)
    - `roles`
    - `permissions`
    - `content_model` (ID autoincrement BIGINT, sin auditoría - tabla del sistema)
    - Tables pivot: `role_user`, `permission_role`, `user_permission`

3. ✅ Incluir Seeders:
    - `RolesAndPermissionsSeeder` (admin, editor, viewer + permisos base)
    - `AdminUserSeeder` (admin@template.com)
    - `DatabaseSeeder` (orquestador)

4. ✅ Incluir Traits y Helpers:
    - `HasFilters`
    - `ResolvesUuidToPkid`
    - `ApiResponse`
    - `BaseModel` (generación automática de UUID)

5. ✅ Configurar Docker + docker-compose
6. ✅ Configurar GitHub Actions para deploy
7. ✅ Documentar en README cómo clonar y personalizar

#### Fase 2: Script Python de Generación (Futuro)

- **NO crear todavía** → Primero terminar web_template_back
- Será **externo** al proyecto
- Entrada: JSON con estructura de tabla + relaciones
- Salida: Todos los archivos del módulo (migración → route)
- Generará también interfaces TS para frontend (futuro)

#### Fase 3: Uso del Template

Cuando se inicie un nuevo proyecto:

1. Clonar `web_template_back`
2. Renombrar proyecto
3. Cambiar credenciales en `AdminUserSeeder`
4. Añadir/modificar prefijos de tablas
5. Ejecutar `composer install`
6. Ejecutar `php artisan migrate:fresh --seed`
7. Configurar `.env` (DB, APP_NAME, etc.)
8. Usar **script Python** para generar nuevos módulos

---

### 🎯 TODOs Críticos

#### Backend (web_template_back)

- [ ] **Crear Trait global** para scopes comunes (`activos`, `inactivos`) SE ESPERA ADEMAS UN SCOPE GLOBAL DINAMICO 
- [ ] **Crear Trait global** para filtros de auditoría (evitar duplicación en cada Filter)
- [ ] **Migración para `deleted_by_id`** en tablas existentes
- [ ] **Actualizar Services existentes** para registrar `deleted_by_id` en método `eliminar()`
- [ ] Documentar relaciones de auditoría (`createdBy()`, `updatedBy()`, `deletedBy()`)
- [ ] Crear seeder de ejemplo para `ContentTypes`
- [ ] Validar que `ResolvesUuidToPkid` maneje correctamente ambos tipos de ID

#### Frontend (proyecto separado - futuro)

- [ ] Mapeo completo de patrón de servicios Angular
- [ ] Guards de permisos reutilizables
- [ ] Directivas de permisos (`*hasPermission`)
- [ ] Componentes reutilizables para CRUD

#### Automatización (script Python - futuro)

- [ ] Definir formato JSON de entrada
- [ ] Crear templates Jinja2 para todos los componentes
- [ ] Implementar CLI interface
- [ ] Validación de estructura JSON
- [ ] Generación de interfaces TypeScript
- [ ] Generación de tests unitarios (opcional)

---

### 📊 Métricas Actuales del Proyecto Peru_Lex

**Módulos Implementados**: 25+

- Alumnos, Profesores, Cursos, Matrículas, Pagos, Tareas, etc.

**Estructura por Módulo** (estandarizada):

- 1 Migración
- 1 Model
- 1 Filter
- 3 Requests (Base, Store, Update)
- 1 Resource
- 1 Service
- 1 Controller
- 1 Route file
- **Total**: ~9-10 archivos por módulo

**Patrones Confirmados**:

- ✅ Service Layer Pattern
- ✅ Repository implícito (Eloquent)
- ✅ Filters dinámicos (QueryFilter)
- ✅ Respuestas API estandarizadas (ApiResponse)
- ✅ Soft Deletes universal
- ✅ Auditoría completa

---

### 📝 Notas Finales

1. **Frontend es proyecto separado** → Por ahora enfocarse solo en BACK
2. **Script Python se definirá después** → Primero completar web_template_back
3. **Validaciones de DNI/RUC**: Ideal pero no hay API contratada (hay opciones gratuitas)
4. **Paginación**: No especificada todavía (añadir cuando se necesite)
5. **Tests unitarios**: No implementados aún (futuro)

---

**Fecha de Creación**: 6 de abril de 2026
**Autor**: Sistema de Documentación - Proyecto Peru Lex
**Versión**: 2.0.0
**Última Actualización**: 6 de abril de 2026

**Contacto**: gabrielsulca159@gmail.com (Desarrollador principal)

---

## 🚀 Próximo Paso Inmediato

**Configurar `web_template_back` con:**

1. Migraciones base (users, roles, permissions, content_model)
2. Seeders (RolesAndPermissions, AdminUser)
3. Traits y Helpers listos para usar
4. Documentación completa de clonación

Una vez listo el template, se procederá a definir el script Python de generación de módulos.
