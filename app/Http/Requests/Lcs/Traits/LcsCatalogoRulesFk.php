<?php

namespace App\Http\Requests\Lcs\Traits;

use Illuminate\Validation\Rule;

/**
 * Validación de la FK hacia `lcs_catalogo`: UN trait por tabla relacionada, compartido por todos los
 * módulos que la referencian (ver Requests y Traits.md#Trait por FK). Lo fijo de la tabla (tipo, conexión,
 * soft delete y mensajes) vive aquí; el campo y la modalidad los pasa cada módulo al llamarlo.
 *
 * @fk-table lcs_catalogo
 */
trait LcsCatalogoRulesFk
{
    /**
     * $field: nombre del campo en el módulo que lo usa (catalogo_id, u otro si hay varias FK a la misma tabla).
     * $modality: 'required' | 'sometimes' | 'nullable' -- la decide el módulo (columna NOT NULL o no).
     * Una obligatoriedad condicional (required_if...) no entra aquí: se declara en el Validates del módulo.
     */
    protected function getCatalogoRules(string $field = 'catalogo_id', string $modality = 'required'): array
    {
        return [
            $field => [$modality, 'string', Rule::exists('dblcs.lcs_catalogo', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function getCatalogoMensajes(string $field = 'catalogo_id'): array
    {
        return [
            "{$field}.required" => "El campo {$field} es requerido",
            "{$field}.string" => "El campo {$field} debe ser texto",
            "{$field}.exists" => "El valor de {$field} no existe",
        ];
    }
}
