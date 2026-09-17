<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra el content_model 'dashboard' y su único permiso
 * (can_view_dashboard) — gatea el menú "Dashboard" en el sidebar, que antes
 * estaba hardcodeado sin permiso en admin-shell.ts. No es un recurso CRUD
 * como los demás content_model: solo existe para que el link a la pantalla
 * de aterrizaje sea, igual que el resto del sidebar, 100% data-driven desde
 * `siaw_menus`.
 */
class SiawDashboardAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dbsiaw';

        // 1. Registrar el modelo (idempotente por unique en app_model)
        $model = DB::connection($connection)->table('siaw_content_model')
            ->where('app_model', 'dashboard')
            ->first();

        if (!$model) {
            $modelPkid = DB::connection($connection)->table('siaw_content_model')->insertGetId([
                'id'             => Str::uuid()->toString(),
                'app_label'      => 'siaw',
                'app_model'      => 'dashboard',
                'nombre_display' => 'Dashboard',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        } else {
            $modelPkid = $model->pkid;
        }

        // 2. Crear el permiso único (idempotente por unique en codename)
        $existing = DB::connection($connection)->table('siaw_content_permisos')
            ->where('codename', 'can_view_dashboard')
            ->first();

        $permisoPkid = $existing
            ? $existing->pkid
            : DB::connection($connection)->table('siaw_content_permisos')->insertGetId([
                'id'               => Str::uuid()->toString(),
                'content_model_id' => $modelPkid,
                'codename'         => 'can_view_dashboard',
                'desc'             => 'Puede ver el panel de aterrizaje del dashboard administrativo',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

        // 3. Asignar el permiso a isSuperUser e isAdmin (idempotente)
        $roles = DB::connection($connection)
            ->table('siaw_roles')
            ->whereIn('slug', ['isSuperUser', 'isAdmin'])
            ->get();

        foreach ($roles as $rol) {
            $exists = DB::connection($connection)->table('siaw_permiso_rol')
                ->where('permiso_id', $permisoPkid)
                ->where('rol_id', $rol->pkid)
                ->exists();

            if (!$exists) {
                DB::connection($connection)->table('siaw_permiso_rol')->insert([
                    'id'         => Str::uuid()->toString(),
                    'permiso_id' => $permisoPkid,
                    'rol_id'     => $rol->pkid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
