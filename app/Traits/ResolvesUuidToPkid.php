<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * ResolvesUuidToPkid – Convierte UUIDs (id) a PKIDs (pkid) antes de
 * guardar en base de datos, ya que las FKs internas apuntan a pkid.
 *
 * Uso en Service:
 *   $data = $this->resolveIds($data, [
 *       'categoria_id' => 'categorias',
 *       'proveedor_id' => 'proveedores',
 *   ]);
 */
trait ResolvesUuidToPkid
{
    /**
     * Convierte un UUID a PKID para una tabla dada.
     * Si el valor ya es numérico o null, lo retorna sin cambios.
     */
    protected function resolveId(mixed $value, string $table): mixed
    {
        if (is_null($value) || is_numeric($value)) {
            return $value;
        }

        return DB::table($table)->where('id', $value)->value('pkid') ?? $value;
    }

    /**
     * Convierte múltiples campos UUID → PKID en un array de datos.
     *
     * @param array $data   Array de datos del request
     * @param array $fields ['campo_id' => 'nombre_tabla', ...]
     */
    protected function resolveIds(array $data, array $fields): array
    {
        foreach ($fields as $field => $table) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->resolveId($data[$field], $table);
            }
        }

        return $data;
    }
}
