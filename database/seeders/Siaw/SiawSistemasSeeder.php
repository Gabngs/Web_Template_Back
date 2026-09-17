<?php

namespace Database\Seeders\Siaw;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siembra el único `siaw_sistemas` que necesita este proyecto — sin esto,
 * `siaw_menus` no tiene ningún `sistema_id` válido al que apuntar.
 */
class SiawSistemasSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dbsiaw';

        $existing = DB::connection($connection)->table('siaw_sistemas')
            ->where('codigo', 'LCS')
            ->first();

        if (!$existing) {
            DB::connection($connection)->table('siaw_sistemas')->insert([
                'id'         => Str::uuid()->toString(),
                'codigo'     => 'LCS',
                'descripcion' => 'Laravel Core Standard',
                'activo'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
