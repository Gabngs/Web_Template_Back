<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder de ejemplo — registra el modelo siaw_content_permisos
 * y sus 4 permisos estándar en el rol SuperUser.
 *
 * Patrón a replicar al crear cualquier módulo nuevo de negocio:
 * php artisan db:seed --class="Database\Seeders\{Modulo}\{Modulo}{Modelo}PermisosSeeder"
 */
class SiawPermisosAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dbsiaw';

        // 1. Registrar el modelo (idempotente por unique en app_model)
        $model = DB::connection($connection)->table('siaw_content_model')
            ->where('app_model', 'content_permisos')
            ->first();

        if (!$model) {
            $modelPkid = DB::connection($connection)->table('siaw_content_model')->insertGetId([
                'id'             => Str::uuid()->toString(),
                'app_label'      => 'siaw',
                'app_model'      => 'content_permisos',
                'nombre_display' => 'Permisos del sistema',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        } else {
            $modelPkid = $model->pkid;
        }

        // 2. Crear los 4 permisos estándar (idempotente por unique en codename)
        $permisos = [
            ['codename' => 'can_view_content_permisos',   'desc' => 'Puede visualizar la lista de permisos'],
            ['codename' => 'can_create_content_permisos', 'desc' => 'Puede crear nuevos permisos'],
            ['codename' => 'can_update_content_permisos', 'desc' => 'Puede modificar permisos existentes'],
            ['codename' => 'can_delete_content_permisos', 'desc' => 'Puede eliminar permisos'],
        ];

        $permisoPkids = [];
        foreach ($permisos as $permiso) {
            $existing = DB::connection($connection)->table('siaw_content_permisos')
                ->where('codename', $permiso['codename'])
                ->first();

            if ($existing) {
                $permisoPkids[] = $existing->pkid;
            } else {
                $permisoPkids[] = DB::connection($connection)->table('siaw_content_permisos')->insertGetId([
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
        $roles = DB::connection($connection)
            ->table('siaw_roles')
            ->whereIn('slug', ['isSuperUser', 'isAdmin'])
            ->get();

        foreach ($roles as $rol) {
            foreach ($permisoPkids as $permisoPkid) {
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
}
