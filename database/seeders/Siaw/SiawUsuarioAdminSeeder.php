<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SiawUsuarioAdminSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'gabrielsulca159@gmail.com';

    public function run(): void
    {
        $now  = now();
        $conn = 'dbsiaw';

        $rol = DB::connection($conn)
            ->table('siaw_roles')
            ->where('slug', 'isSuperUser')
            ->first();

        $usuario = DB::connection($conn)
            ->table('siaw_usuarios')
            ->where('email', self::ADMIN_EMAIL)
            ->first();

        if (!$usuario) {
            DB::connection($conn)->table('siaw_usuarios')->insert([
                'id'         => Str::uuid()->toString(),
                'nombre'     => 'Gabriel',
                'apellidos'  => 'Sulca',
                'email'      => self::ADMIN_EMAIL,
                'password'   => Hash::make('25478631sS'),
                'activo'     => 1,
                'rol_id'     => $rol?->pkid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $usuario = DB::connection($conn)
                ->table('siaw_usuarios')
                ->where('email', self::ADMIN_EMAIL)
                ->first();
        }

        // Insert crudo (DB::table) no dispara el evento "created" del modelo
        // que autogenera "codigo" — se llena aquí igual, con el mismo formato.
        if ($usuario && empty($usuario->codigo)) {
            DB::connection($conn)->table('siaw_usuarios')
                ->where('pkid', $usuario->pkid)
                ->update(['codigo' => str_pad((string) $usuario->pkid, 5, '0', STR_PAD_LEFT)]);
        }

        if ($usuario && $rol) {
            $exists = DB::connection($conn)->table('siaw_rol_usuario')
                ->where('usuario_id', $usuario->pkid)
                ->where('rol_id', $rol->pkid)
                ->exists();

            if (!$exists) {
                DB::connection($conn)->table('siaw_rol_usuario')->insert([
                    'id'         => Str::uuid()->toString(),
                    'usuario_id' => $usuario->pkid,
                    'rol_id'     => $rol->pkid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
