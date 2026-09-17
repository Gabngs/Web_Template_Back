# LCS Backend — Laravel Core Standard

Backend base en Laravel 12: módulo **SIAW** (usuarios, roles, permisos, menús) + **Auth** (login con desafío RSA + Sanctum) + despliegue listo para K3s. Pensado como punto de partida de cualquier proyecto nuevo que siga el estándar — clonar, renombrar el prefijo `lcs` (opcional, ver `docs/CONFIGURAR_SECRETS.md`) y agregar el primer módulo de negocio.

## Stack

- Laravel 12 / PHP 8.4, Sanctum, `essa/api-tool-kit` (filtros de query), `darkaonline/l5-swagger`
- MariaDB — 3 conexiones fijas: `dblcs` (negocio propio), `dbsiaw` (usuarios/roles/permisos), `dbsincro` (infra: ledger de migraciones + `seeders_log`, jobs, cache, sessions)
- Redis (cache), Resend (correo transaccional en producción)
- Docker Compose (Sail) en local, K3s + GitHub Actions en producción

## Arranque local

```bash
bash setup.sh   # .env interactivo (opcional, o copiá .env.example a mano) — se autoelimina
bash start.sh   # build + up + migrate + seed + claves RSA + swagger
```

- API: http://localhost:8877
- Swagger: http://localhost:8877/api/documentation
- Mailpit: http://localhost:8026

Usuario admin sembrado por `SiawUsuarioAdminSeeder` — ver esa clase para las credenciales iniciales.

## Documentación

- [`docs/CONFIG_bases-datos-migraciones-seeders-rutas.md`](docs/CONFIG_bases-datos-migraciones-seeders-rutas.md) — cómo están armadas las 3 conexiones, el ledger centralizado de migraciones/seeders y las rutas
- [`docs/CONFIGURAR_SECRETS.md`](docs/CONFIGURAR_SECRETS.md) — checklist de secrets al clonar este repo (GitHub Actions, K3s, RSA, correo)
- [`docs/RUNBOOK_BD_Y_SEGURIDAD.md`](docs/RUNBOOK_BD_Y_SEGURIDAD.md) — usuarios de BD acotados, rotación de contraseñas, endurecimiento del VPS

El estándar completo (patrón de módulo, seguridad, despliegue) vive en el Vault de Obsidian: `Template Estandar Backend/`.

## Estructura

```
app/
├── Console/Commands/          RotateRsaKeys, Siaw/PoblarSistemaIdContentPermisos
├── Database/                  CentralMigrationRepository (ledger unificado en dbsincro)
├── Http/Controllers/Api/      AuthController + Siaw/*
├── Http/Middleware/           ValidateSessionKey, RequierePermiso, ForzarCambioPassword, SecurityHeaders
├── Models/dbsiaw/             Modelos de usuarios/roles/permisos/menús
└── Services/Siaw/             Un service por recurso SIAW (inyecta CrudService)

database/
├── migrations/{Siaw,Sincro}/  Una carpeta por módulo — se auto-registran (ver AppServiceProvider)
└── seeders/Siaw/               10 seeders base (roles, usuario admin, sistema LCS, menús)

routes/
├── api/api.php                 Rutas públicas de auth (login, challenge, public-key)
└── modules/siaw_*.php          Un archivo por recurso — auth centralizada en RouteServiceProvider

k8s/                            Manifiestos de producción (namespace lcs-prod)
docker/, Dockerfile, docker-compose.yml, setup.sh, start.sh   Local + imagen de producción
```

## Agregar el primer módulo de negocio

1. `database/migrations/{Modulo}/` con `protected $connection = 'dblcs'` — se auto-registra.
2. Controllers/Services/Requests siguiendo el patrón de `Siaw/*` (ver `Service Patron/` en el Vault).
3. `routes/modules/{modulo}.php` — no repetir el middleware de auth, ya está centralizado en `RouteServiceProvider`.
4. Agregar el seeder correspondiente al final del array en `database/seeders/DatabaseSeeder.php`.
