<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra el content_model 'menus' y sus 4 permisos estándar (gatean la
 * pantalla de Mantenimiento de Menús, no el sidebar), y siembra el árbol
 * base de `siaw_menus` que todo proyecto nuevo necesita para no arrancar
 * con el sidebar vacío:
 *
 *   Dashboard               (raíz, gateado por can_view_dashboard)
 *   Parámetros del sistema  (raíz, contenedor puro, sin ruta ni permiso propio)
 *     └─ Modelos y Permisos     → gateado por can_view_content_model (ya existe)
 *     └─ Mantenimiento de Menús → gateado por can_view_menus (este seeder)
 *     └─ Control de usuarios    → gateado por can_view_usuarios (este seeder)
 *     └─ Control de roles       → gateado por can_view_roles (SiawRolesAdminSeeder)
 *     └─ Mantenimiento de Sistemas → gateado por can_view_sistemas (SiawSistemasAdminSeeder)
 *
 * Debe correr después de SiawSistemasSeeder, SiawModelosAdminSeeder,
 * SiawPermisosAdminSeeder, SiawUsuariosAdminSeeder, SiawRolesAdminSeeder,
 * SiawDashboardAdminSeeder y SiawSistemasAdminSeeder.
 */
class SiawMenusAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dbsiaw';
        $db         = DB::connection($connection);

        // 1. Registrar el content_model 'menus' (idempotente por unique en app_model)
        $model = $db->table('siaw_content_model')->where('app_model', 'menus')->first();

        if (!$model) {
            $modelPkid = $db->table('siaw_content_model')->insertGetId([
                'id'             => Str::uuid()->toString(),
                'app_label'      => 'siaw',
                'app_model'      => 'menus',
                'nombre_display' => 'Menús del sistema',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        } else {
            $modelPkid = $model->pkid;
        }

        // 2. Crear los 4 permisos estándar (idempotente por unique en codename)
        $permisos = [
            ['codename' => 'can_view_menus',   'desc' => 'Puede visualizar la lista de menús'],
            ['codename' => 'can_create_menus', 'desc' => 'Puede crear nuevos menús'],
            ['codename' => 'can_update_menus', 'desc' => 'Puede modificar menús existentes'],
            ['codename' => 'can_delete_menus', 'desc' => 'Puede eliminar menús'],
        ];

        $permisoPkids = [];
        foreach ($permisos as $permiso) {
            $existing = $db->table('siaw_content_permisos')->where('codename', $permiso['codename'])->first();

            if ($existing) {
                $permisoPkids[] = $existing->pkid;
            } else {
                $permisoPkids[] = $db->table('siaw_content_permisos')->insertGetId([
                    'id'               => Str::uuid()->toString(),
                    'content_model_id' => $modelPkid,
                    'codename'         => $permiso['codename'],
                    'desc'             => $permiso['desc'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }

        // 3. Asignar permisos a isSuperUser e isAdmin (idempotente)
        $roles = $db->table('siaw_roles')->whereIn('slug', ['isSuperUser', 'isAdmin'])->get();

        foreach ($roles as $rol) {
            foreach ($permisoPkids as $permisoPkid) {
                $exists = $db->table('siaw_permiso_rol')
                    ->where('permiso_id', $permisoPkid)
                    ->where('rol_id', $rol->pkid)
                    ->exists();

                if (!$exists) {
                    $db->table('siaw_permiso_rol')->insert([
                        'id'         => Str::uuid()->toString(),
                        'permiso_id' => $permisoPkid,
                        'rol_id'     => $rol->pkid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 4. Árbol base de siaw_menus (idempotente por titulo + parent_id).
        //    2 niveles: Parámetros del sistema (raíz, contenedor puro, sin
        //    ruta ni permiso propio) → sub-features. Ya no existe el grupo
        //    "Administrativo" que envolvía a "Parámetros del sistema" —
        //    era un contenedor sin valor (un solo hijo) que solo agregaba un
        //    nivel más al sidebar.
        $sistema = $db->table('siaw_sistemas')->where('codigo', 'LCS')->first();
        if (!$sistema) {
            // SiawSistemasSeeder no corrió — no hay a qué sistema atar los menús.
            return;
        }

        // "Dashboard" antes vivía hardcodeado en admin-shell.ts, sin pasar
        // por permisos ni por siaw_menus — ahora es un menú raíz sembrado
        // como cualquier otro, gateado por can_view_dashboard.
        $dashboardPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => null,
            'titulo'      => 'Dashboard',
            'descripcion' => 'Panel de aterrizaje del área administrativa',
            'ruta'        => '/dashboard',
            'nombre_icon' => 'home',
            'orden'       => 0,
            'dashboard'   => false,
        ]);

        $parametrosPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => null,
            'titulo'      => 'Parámetros del sistema',
            'descripcion' => 'Modelos, permisos, menús y usuarios del sistema',
            'ruta'        => null,
            'nombre_icon' => 'sliders-h',
            'orden'       => 1,
            'dashboard'   => false,
        ]);

        $modelosPermisosPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => $parametrosPkid,
            'titulo'      => 'Modelos y Permisos',
            'descripcion' => 'Catálogo de modelos y permisos del sistema',
            'ruta'        => '/dashboard/parametros-sistema/modelos-permisos',
            'nombre_icon' => 'database',
            'orden'       => 1,
            'dashboard'   => true,
        ]);

        $menusMenuPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => $parametrosPkid,
            'titulo'      => 'Mantenimiento de Menús',
            'descripcion' => 'Alta, edición y baja de los menús del sidebar',
            'ruta'        => '/dashboard/parametros-sistema/mantenimiento-menus',
            'nombre_icon' => 'sitemap',
            'orden'       => 2,
            'dashboard'   => false,
        ]);

        $usuariosMenuPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => $parametrosPkid,
            'titulo'      => 'Control de usuarios',
            'descripcion' => 'Alta, edición y baja de usuarios',
            'ruta'        => '/dashboard/parametros-sistema/control-usuarios',
            'nombre_icon' => 'users',
            'orden'       => 3,
            'dashboard'   => true,
        ]);

        $rolesMenuPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => $parametrosPkid,
            'titulo'      => 'Control de roles',
            'descripcion' => 'Alta, edición y baja de roles',
            'ruta'        => '/dashboard/parametros-sistema/mantenimiento-roles',
            'nombre_icon' => 'shield',
            'orden'       => 4,
            'dashboard'   => true,
        ]);

        $sistemasMenuPkid = $this->findOrCreateMenu($db, $now, [
            'sistema_id'  => $sistema->pkid,
            'parent_id'   => $parametrosPkid,
            'titulo'      => 'Mantenimiento de Sistemas',
            'descripcion' => 'Alta, edición y baja de sistemas',
            'ruta'        => '/dashboard/parametros-sistema/mantenimiento-sistemas',
            'nombre_icon' => 'server',
            'orden'       => 5,
            'dashboard'   => true,
        ]);

        // 5. Vincular siaw_menu_permiso — "Modelos y Permisos" reusa el
        //    permiso que ya gatea esa tab, el resto usa los permisos
        //    sembrados arriba en este mismo run / en SiawUsuariosAdminSeeder,
        //    SiawRolesAdminSeeder, SiawDashboardAdminSeeder y SiawSistemasAdminSeeder.
        $canViewContentModel = $db->table('siaw_content_permisos')->where('codename', 'can_view_content_model')->first();
        $canViewMenus        = $db->table('siaw_content_permisos')->where('codename', 'can_view_menus')->first();
        $canViewUsuarios     = $db->table('siaw_content_permisos')->where('codename', 'can_view_usuarios')->first();
        $canViewRoles        = $db->table('siaw_content_permisos')->where('codename', 'can_view_roles')->first();
        $canViewDashboard    = $db->table('siaw_content_permisos')->where('codename', 'can_view_dashboard')->first();
        $canViewSistemas     = $db->table('siaw_content_permisos')->where('codename', 'can_view_sistemas')->first();

        if ($canViewContentModel) {
            $this->findOrCreateMenuPermiso($db, $now, $modelosPermisosPkid, $canViewContentModel->pkid);
        }
        if ($canViewMenus) {
            $this->findOrCreateMenuPermiso($db, $now, $menusMenuPkid, $canViewMenus->pkid);
        }
        if ($canViewUsuarios) {
            $this->findOrCreateMenuPermiso($db, $now, $usuariosMenuPkid, $canViewUsuarios->pkid);
        }
        if ($canViewRoles) {
            $this->findOrCreateMenuPermiso($db, $now, $rolesMenuPkid, $canViewRoles->pkid);
        }
        if ($canViewDashboard) {
            $this->findOrCreateMenuPermiso($db, $now, $dashboardPkid, $canViewDashboard->pkid);
        }
        if ($canViewSistemas) {
            $this->findOrCreateMenuPermiso($db, $now, $sistemasMenuPkid, $canViewSistemas->pkid);
        }
    }

    /**
     * Busca un menú por titulo+parent_id o lo crea y le calcula `clave`
     * (materialized path), igual que SiawMenusService::calcularClave() —
     * acá se seedea directo contra la tabla, sin pasar por el service.
     */
    private function findOrCreateMenu($db, $now, array $data): int
    {
        $existing = $db->table('siaw_menus')
            ->where('titulo', $data['titulo'])
            ->where(function ($q) use ($data) {
                $data['parent_id'] === null ? $q->whereNull('parent_id') : $q->where('parent_id', $data['parent_id']);
            })
            ->first();

        if ($existing) {
            return $existing->pkid;
        }

        $pkid = $db->table('siaw_menus')->insertGetId([
            'id'           => Str::uuid()->toString(),
            'sistema_id'   => $data['sistema_id'],
            'parent_id'    => $data['parent_id'],
            'titulo'       => $data['titulo'],
            'descripcion'  => $data['descripcion'],
            'ruta'         => $data['ruta'],
            'nombre_icon'  => $data['nombre_icon'],
            'orden'        => $data['orden'],
            'activo'       => true,
            'dashboard'    => $data['dashboard'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $parentClave = $data['parent_id']
            ? $db->table('siaw_menus')->where('pkid', $data['parent_id'])->value('clave')
            : null;

        $clave = $parentClave ? "{$parentClave}-{$pkid}" : (string) $pkid;

        $db->table('siaw_menus')->where('pkid', $pkid)->update(['clave' => $clave]);

        return $pkid;
    }

    private function findOrCreateMenuPermiso($db, $now, int $menuPkid, int $permisoPkid): void
    {
        $exists = $db->table('siaw_menu_permiso')
            ->where('menu_id', $menuPkid)
            ->where('permiso_id', $permisoPkid)
            ->exists();

        if (!$exists) {
            $db->table('siaw_menu_permiso')->insert([
                'id'         => Str::uuid()->toString(),
                'menu_id'    => $menuPkid,
                'permiso_id' => $permisoPkid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
