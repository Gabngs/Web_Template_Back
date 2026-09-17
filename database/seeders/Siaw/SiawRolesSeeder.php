<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiawRolesSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dbsiaw';

        // Roles base, genéricos a cualquier módulo de negocio.
        // Agrega roles específicos del dominio (ej: isVendedor) cuando se
        // definan los primeros módulos de negocio.
        $roles = [
            [
                'name'        => 'Super Usuario',
                'slug'        => 'isSuperUser',
                'descripcion' => 'Acceso total al sistema sin restricciones.',
            ],
            [
                'name'        => 'Administrador',
                'slug'        => 'isAdmin',
                'descripcion' => 'Gestión de usuarios, roles y configuración general.',
            ],
        ];

        foreach ($roles as $rol) {
            DB::connection($connection)->table('siaw_roles')->insertOrIgnore([
                'id'          => Str::uuid()->toString(),
                'name'        => $rol['name'],
                'slug'        => $rol['slug'],
                'guard_name'  => 'api',
                'descripcion' => $rol['descripcion'],
                'activo'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
