<?php

namespace Database\Seeders\Lcs;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siembra el catálogo estándar `catalogo_tipodato` (lcs_catalogo +
 * lcs_catalogo_det). Lo usa siaw_parametros.tipodato_id.
 *
 * El orden de inserción fija los pkid (1..8): son las constantes
 * LcsCatalogo::TIPODATO y LcsCatalogoDet::TIPODATO_*. No reordenar.
 */
class LcsCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $now        = now();
        $connection = 'dblcs';

        $existe = DB::connection($connection)->table('lcs_catalogo')
            ->where('codigo', 'catalogo_tipodato')->first();

        if ($existe) {
            return;
        }

        $catalogoId = DB::connection($connection)->table('lcs_catalogo')->insertGetId([
            'id'          => Str::uuid()->toString(),
            'codigo'      => 'catalogo_tipodato',
            'nombre'      => 'Tipos de dato',
            'descripcion' => 'Tipo con el que se interpreta siaw_parametros.valor',
            'activo'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], 'pkid');

        $tipos = [
            ['string',   'Texto'],
            ['int',      'Entero'],
            ['decimal',  'Decimal'],
            ['boolean',  'Booleano'],
            ['date',     'Fecha'],
            ['time',     'Hora'],
            ['datetime', 'Fecha y hora'],
            ['json',     'JSON'],
        ];

        foreach ($tipos as [$codigo, $nombre]) {
            DB::connection($connection)->table('lcs_catalogo_det')->insert([
                'id'          => Str::uuid()->toString(),
                'catalogo_id' => $catalogoId,
                'codigo'      => $codigo,
                'nombre'      => $nombre,
                'activo'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
