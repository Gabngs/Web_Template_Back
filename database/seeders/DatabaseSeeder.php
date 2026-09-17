<?php

namespace Database\Seeders;

use Database\Seeders\Siaw\SiawRolesSeeder;
use Database\Seeders\Siaw\SiawUsuarioAdminSeeder;
use Database\Seeders\Siaw\SiawModelosAdminSeeder;
use Database\Seeders\Siaw\SiawPermisosAdminSeeder;
use Database\Seeders\Siaw\SiawSistemasSeeder;
use Database\Seeders\Siaw\SiawUsuariosAdminSeeder;
use Database\Seeders\Siaw\SiawRolesAdminSeeder;
use Database\Seeders\Siaw\SiawDashboardAdminSeeder;
use Database\Seeders\Siaw\SiawSistemasAdminSeeder;
use Database\Seeders\Siaw\SiawMenusAdminSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // El orden importa: roles → usuario (necesita el rol) → catálogo de modelos → permisos (necesita el rol)
        // → sistema/usuarios (necesitan roles) → menús (necesita sistema + los permisos ya sembrados arriba)
        $seeders = [
            SiawRolesSeeder::class,          // 1. Roles base (SuperUser, Admin)
            SiawUsuarioAdminSeeder::class,    // 2. Usuario admin + asignación de rol SuperUser
            SiawModelosAdminSeeder::class,    // 3. Modelo siaw_content_model registrándose a sí mismo
            SiawPermisosAdminSeeder::class,   // 4. Permisos de ejemplo del módulo siaw_content_permisos
            SiawSistemasSeeder::class,        // 5. Sistema base LCS (necesario para siaw_menus)
            SiawUsuariosAdminSeeder::class,   // 6. content_model 'usuarios' + sus 4 permisos
            SiawRolesAdminSeeder::class,      // 7. content_model 'roles' + sus 4 permisos
            SiawDashboardAdminSeeder::class,  // 8. content_model 'dashboard' + can_view_dashboard
            SiawSistemasAdminSeeder::class,   // 9. content_model 'sistemas' + sus 4 permisos
            SiawMenusAdminSeeder::class,      // 10. content_model 'menus' + árbol base del sidebar
        ];

        // db:seed corre en cada deploy (k8s/05-job-migrate.yaml). Sin este
        // registro, cada seeder no-idempotente reinserta sus filas en cada
        // push a main. Acá cada seeder corre una única vez: se registra en
        // seeders_log (dbsincro) apenas termina, y las corridas siguientes
        // solo ejecutan las clases nuevas que se agreguen arriba.
        $log        = DB::connection('dbsincro')->table('seeders_log');
        $yaCorridos = $log->pluck('seeder')->all();

        foreach ($seeders as $seederClass) {
            if (in_array($seederClass, $yaCorridos, true)) {
                continue;
            }

            $this->call($seederClass);

            $log->insert([
                'seeder' => $seederClass,
                'ran_at' => now(),
            ]);
        }
    }
}
