<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'dbsiaw'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | LCS (Laravel Core Standard) nace con 3 conexiones MariaDB fijas —
    | ver Template Estandar Backend/Service Patron/Conexiones, Migraciones
    | y Rutas.md:
    |
    | dblcs     → Negocio propio de este proyecto (tablas {prefijo}_*).
    |             Vacía hasta que se agregue el primer módulo de negocio.
    | dbsiaw    → Usuarios/roles/permisos/menús compartidos (tablas siaw_*).
    |             De acá salen los modelos que resuelven created_by/updated_by/
    |             deleted_by en cualquier módulo.
    | dbsincro  → Infraestructura: ledger centralizado de migraciones +
    |             seeders_log, sessions, cache, jobs. No guarda tablas de
    |             negocio de ningún módulo.
    |
    | Regla dura: el nombre de conexión es literal "db{prefijo}", sin
    | prefijo de driver (nunca "mysql_dbxxx") — el driver es un detalle de
    | 'driver' => 'mariadb' dentro de cada entrada, no algo que el resto del
    | código necesite ver.
    |
    | 'sqlite' se mantiene solo para tests (phpunit.xml usa DB_CONNECTION=sqlite
    | en memoria) — no se usa en local/prod.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        // ─── Negocio propio del proyecto — tablas {prefijo}_* ────────────
        'dblcs' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_LCS_HOST', '127.0.0.1'),
            'port' => env('DB_LCS_PORT', '3306'),
            'database' => env('DB_LCS_DATABASE', 'db_lcs'),
            'username' => env('DB_RUNTIME_USER') ?: env('DB_LCS_USERNAME', 'root'),
            'password' => env('DB_RUNTIME_PASS') ?: env('DB_LCS_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ─── Usuarios, roles, permisos y auditoría — tablas siaw_* ───────
        'dbsiaw' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_SIAW_HOST', '127.0.0.1'),
            'port' => env('DB_SIAW_PORT', '3306'),
            'database' => env('DB_SIAW_DATABASE', 'db_siaw'),
            'username' => env('DB_RUNTIME_USER') ?: env('DB_SIAW_USERNAME', 'root'),
            'password' => env('DB_RUNTIME_PASS') ?: env('DB_SIAW_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ─── Infraestructura — ledger de migraciones, seeders_log, jobs,
        //     sessions, cache ──────────────────────────────────────────────
        'dbsincro' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_SINCRO_HOST', '127.0.0.1'),
            'port' => env('DB_SINCRO_PORT', '3306'),
            'database' => env('DB_SINCRO_DATABASE', 'db_sincro'),
            'username' => env('DB_RUNTIME_USER') ?: env('DB_SINCRO_USERNAME', 'root'),
            'password' => env('DB_RUNTIME_PASS') ?: env('DB_SINCRO_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | El binding real de 'migration.repository' está reescrito en
    | AppServiceProvider para forzar siempre la conexión dbsincro
    | (ver app/Database/CentralMigrationRepository.php) — esta tabla es
    | solo el nombre, no la conexión.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
