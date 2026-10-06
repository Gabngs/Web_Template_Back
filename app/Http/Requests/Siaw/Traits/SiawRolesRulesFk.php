<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

/**
 * Validación de la FK hacia `siaw_roles`: UN trait por tabla relacionada, compartido por todos los
 * módulos que la referencian (ver Requests y Traits.md#Trait por FK). Lo fijo de la tabla (tipo, conexión,
 * soft delete y mensajes) vive aquí; el campo y la modalidad los pasa cada módulo al llamarlo.
 *
 * @fk-table siaw_roles
 */
trait SiawRolesRulesFk
{
    /**
     * $field: nombre del campo en el módulo que lo usa (roles_id, u otro si hay varias FK a la misma tabla).
     * $modality: 'required' | 'sometimes' | 'nullable' -- la decide el módulo (columna NOT NULL o no).
     * Una obligatoriedad condicional (required_if...) no entra aquí: se declara en el Validates del módulo.
     */
    protected function getRolesRules(string $field = 'roles_id', string $modality = 'required'): array
    {
        return [
            $field => [$modality, 'string', Rule::exists('dbsiaw.siaw_roles', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function getRolesMensajes(string $field = 'roles_id'): array
    {
        return [
            "{$field}.required" => "El campo {$field} es requerido",
            "{$field}.string" => "El campo {$field} debe ser texto",
            "{$field}.exists" => "El valor de {$field} no existe",
        ];
    }
}
