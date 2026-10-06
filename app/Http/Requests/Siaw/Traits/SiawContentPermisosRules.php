<?php

namespace App\Http\Requests\Siaw\Traits;

trait SiawContentPermisosRules
{
    use SiawContentModelRulesFk;

    /**
     * Reglas para campos que son FK (llegan como UUID desde el frontend).
     * La validación 'exists' confirma que el UUID existe en la tabla antes de persistir; la regla de cada
     * FK vive en su trait compartido (con la conexión y el soft delete de esa tabla).
     * El mapeo UUID -> PKID lo hace el Service después (ver Mapeo UUID PKID.md).
     *
     * $required: 'required' (Store) o 'sometimes' (Update) -- lo único que cambia entre los dos
     * FormRequests; las FK nullable de la tabla siguen siendo 'nullable' en ambos.
     */
    protected function getRelacionesRules(string $required = 'required'): array
    {
        return array_merge(
            $this->getContentModelRules('content_model_id', $required),
        );
    }

    protected function getRelacionesMensajes(): array
    {
        return array_merge(
            $this->getContentModelMensajes('content_model_id'),
        );
    }
}
