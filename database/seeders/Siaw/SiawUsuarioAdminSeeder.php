<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SiawUsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now   = now();
        $conn  = 'dbsiaw';
        // Genérico por defecto (queda commiteado en .env.example a propósito,
        // no expone nada real). El valor real va en .env local (gitignorado)
        // o en el secret lcs-env de K8s en prod — ver docs/CONFIGURAR_SECRETS.md.
        $email = env('SIAW_ADMIN_EMAIL_INICIAL', 'admin@example.com');

        $rol = DB::connection($conn)
            ->table('siaw_roles')
            ->where('slug', 'isSuperUser')
            ->first();

        $usuario = DB::connection($conn)
            ->table('siaw_usuarios')
            ->where('email', $email)
            ->first();

        if (!$usuario) {
            DB::connection($conn)->table('siaw_usuarios')->insert([
                'id'         => Str::uuid()->toString(),
                'nombre'     => 'Admin',
                'apellidos'  => 'LCS',
                'email'      => $email,
                'password'   => Hash::make(env('SIAW_ADMIN_PASSWORD_INICIAL', 'Cambiar123!')),
                'activo'     => 1,
                'rol_id'     => $rol?->pkid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $usuario = DB::connection($conn)
                ->table('siaw_usuarios')
                ->where('email', $email)
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
