<?php

namespace App\Http\Requests\Siaw\Traits;

use App\Http\Requests\Lcs\Traits\LcsCatalogoDetRulesFk;
use App\Models\dbsiaw\SiawSistemas;
use Illuminate\Validation\Rule;

trait SiawParametrosRules
{
    use LcsCatalogoDetRulesFk, SiawSistemasRulesFk;

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
            $this->getSistemasRules('sistema_id', 'nullable'),
            $this->getCatalogoDetRules('tipodato_id', $required),
        );
    }

    protected function getRelacionesMensajes(): array
    {
        return array_merge(
            $this->getSistemasMensajes('sistema_id'),
            $this->getCatalogoDetMensajes('tipodato_id'),
        );
    }

    /**
     * La migración define unique(sistema_id, codigo). Se valida acá (con el pkid del sistema ya resuelto,
     * o NULL = parámetro global) para responder 422 en vez de un 500 por violación de la restricción.
     *
     * $sistemaUuid: sistema_id que llega en el request; si es null/ausente, el del registro actual
     * ($actualSistemaPkid) en Update, o parámetro global en Store.
     */
    protected function uniqueCodigoRule(?string $ignoreId = null, ?int $actualSistemaPkid = null): \Illuminate\Validation\Rules\Unique
    {
        $enviado = $this->has('sistema_id');
        $sistemaPkid = $enviado
            ? ($this->input('sistema_id') ? SiawSistemas::where('id', $this->input('sistema_id'))->value('pkid') : null)
            : $actualSistemaPkid;

        $rule = Rule::unique('dbsiaw.siaw_parametros', 'codigo')
            ->withoutTrashed()
            ->where(fn ($q) => $sistemaPkid === null
                ? $q->whereNull('sistema_id')
                : $q->where('sistema_id', $sistemaPkid));

        return $ignoreId ? $rule->ignore($ignoreId, 'id') : $rule;
    }
}
