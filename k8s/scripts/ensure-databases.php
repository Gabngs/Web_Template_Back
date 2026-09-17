<?php

/**
 * Prepara los usuarios acotados de MariaDB de forma idempotente, ANTES de que
 * el Job de migración corra `php artisan migrate`:
 *
 *   - lcs_app     → solo DML (SELECT/INSERT/UPDATE/DELETE)   → pods api/queue/scheduler
 *   - lcs_migrate → DML + DDL (CREATE/ALTER/INDEX/DROP/...)   → solo este Job
 *   Si no hay contraseña en el entorno, ese usuario se OMITE (no rompe).
 *
 * Las 3 bases fijas del proyecto (db_lcs, db_siaw, db_sincro) ya las crea
 * el ConfigMap `mariadb-init` (k8s/01-mariadb.yaml) en la primera
 * inicialización del volumen — este script no las crea, solo otorga
 * privilegios a los usuarios acotados sobre las 3.
 *
 * Por qué acá y no solo en el ConfigMap: los scripts de
 * /docker-entrypoint-initdb.d de MariaDB corren únicamente la PRIMERA vez que
 * el contenedor inicializa un volumen vacío. En un cluster ya existente (PVC
 * con datos) no tienen efecto. Este script corre en cada deploy y es 100%
 * idempotente (`CREATE USER IF NOT EXISTS`, `ALTER USER`, `GRANT`).
 *
 * Entorno (secret lcs-db-root + lcs-db-admin, montados con `envFrom optional`):
 *   DB_ROOT_PASSWORD                        root de MariaDB (obligatorio)
 *   DB_SIAW_HOST / DB_SIAW_PORT              host/puerto de referencia
 *   DB_APP_USERNAME (def. lcs_app)         + DB_APP_PASSWORD
 *   DB_MIGRATE_USERNAME (def. lcs_migrate) + DB_MIGRATE_PASSWORD
 *
 * No usa Laravel: PDO puro para conectar SIN seleccionar base.
 */

/** Las 3 bases fijas del proyecto — el orden no importa, los GRANT son por base. */
const ALL_DATABASES = ['db_lcs', 'db_siaw', 'db_sincro'];

const APP_PRIVS     = 'SELECT, INSERT, UPDATE, DELETE';
const MIGRATE_PRIVS = 'SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, '
                    . 'CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, REFERENCES';

$exit = 0;

$root = getenv('DB_ROOT_PASSWORD');
if ($root === false || $root === '') {
    fwrite(STDERR, "[ensure-databases] DB_ROOT_PASSWORD no está seteado — no se puede crear/otorgar.\n");
    exit(1);
}

$host = getenv('DB_SIAW_HOST') ?: '127.0.0.1';
$port = getenv('DB_SIAW_PORT') ?: '3306';

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", 'root', $root, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "[ensure-databases] no se pudo conectar como root a {$host}:{$port} :: " . $e->getMessage() . "\n");
    exit(1);
}

ensure_user($pdo, getenv('DB_APP_USERNAME')     ?: 'lcs_app',     getenv('DB_APP_PASSWORD')     ?: '', APP_PRIVS,     $exit);
ensure_user($pdo, getenv('DB_MIGRATE_USERNAME') ?: 'lcs_migrate', getenv('DB_MIGRATE_PASSWORD') ?: '', MIGRATE_PRIVS, $exit);

exit($exit);


// ── Helpers ─────────────────────────────────────────────────────────────────

function ident_ok(string $s): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]+$/', $s);
}

function ensure_user(PDO $pdo, string $user, string $pass, string $privs, int &$exit): void
{
    if ($pass === '') {
        fwrite(STDOUT, "[ensure-databases] usuario '{$user}' omitido (sin contraseña en el entorno).\n");
        return;
    }
    if (! ident_ok($user)) {
        fwrite(STDERR, "[ensure-databases] usuario inválido: '{$user}' — se omite.\n");
        $exit = 1;
        return;
    }

    $q = $pdo->quote($pass);

    try {
        $pdo->exec("CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY {$q}");
        // ALTER sincroniza la contraseña con la del secret (permite rotación
        // desde el pipeline sin tocar SQL a mano).
        $pdo->exec("ALTER USER '{$user}'@'%' IDENTIFIED BY {$q}");

        foreach (ALL_DATABASES as $db) {
            $pdo->exec("GRANT {$privs} ON `{$db}`.* TO '{$user}'@'%'");
        }
        $pdo->exec('FLUSH PRIVILEGES');

        fwrite(STDOUT, "[ensure-databases] OK usuario {$user} ({$privs}) sobre " . count(ALL_DATABASES) . " bases\n");
    } catch (Throwable $e) {
        fwrite(STDERR, "[ensure-databases] FALLO usuario {$user} :: " . $e->getMessage() . "\n");
        $exit = 1;
    }
}
