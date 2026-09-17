<?php

namespace App\Services;

use App\Http\Token;
use App\Models\dbsiaw\SiawAuditLog;
use App\Models\dbsiaw\SiawUsuarios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clase base CRUD, inyectada (nunca extendida) en todos los services de
 * módulo. Centraliza persistencia + auditoría opcional en siaw_audit_log.
 */
class CrudService
{
    /** pkid del usuario de auditoría por defecto (resuelto una vez por instancia). */
    private ?int $fallbackPkid = null;
    private bool $fallbackResuelto = false;

    /**
     * pkid a estampar en las columnas de auditoría: el del usuario del token
     * si es un SiawUsuarios; si no (p. ej. formulario público de almuerzos,
     * cuyo tokenable es un colaborador), el usuario de sistema configurado en
     * `sistemas.usuario_auditoria_fallback_codigo`.
     */
    private function actorPkid(): ?int
    {
        $pkid = Token::pkid();
        if ($pkid !== null) {
            return $pkid;
        }

        if (! $this->fallbackResuelto) {
            $this->fallbackResuelto = true;
            $codigo = config('sistemas.usuario_auditoria_fallback_codigo');
            $this->fallbackPkid = $codigo
                ? SiawUsuarios::withTrashed()->where('codigo', $codigo)->value('pkid')
                : null;
        }

        return $this->fallbackPkid;
    }

    public function create(string $modelClass, array $data, ?string $proceso = null): Model
    {
        $pkid = $this->actorPkid();

        $data['id'] ??= Str::uuid()->toString();

        if ($pkid !== null) {
            $data['created_by_id'] = $pkid;
            $data['updated_by_id'] = $pkid;
        }

        $model = $modelClass::create($data);
    
        if ($proceso) {
            $this->audit(
                proceso: $proceso,
                tipo: SiawAuditLog::TIPO_INSERCION,
                model: $model,
                input: $data,
                output: $model->toArray(),
            );
        }

        return $model;
    }

    public function update(Model $model, array $data, ?string $proceso = null): Model
    {
        $pkid = $this->actorPkid();

        if ($pkid !== null) {
            $data['updated_by_id'] = $pkid;
        }

        $input = $model->toArray();
        $model->update($data);
        $model->refresh();

        if ($proceso) {
            $this->audit(
                proceso: $proceso,
                tipo: SiawAuditLog::TIPO_ACTUALIZACION,
                model: $model,
                input: $input,
                output: $model->toArray(),
            );
        }

        return $model;
    }

    public function delete(Model $model, ?string $proceso = null): Model
    {
        $pkid = $this->actorPkid();

        if ($pkid !== null) {
            $model->deleted_by_id = $pkid;
            $model->save();
        }

        $snapshot = clone $model;
        $model->delete();

        if ($proceso) {
            $this->audit(
                proceso: $proceso,
                tipo: SiawAuditLog::TIPO_ELIMINACION,
                model: $snapshot,
                input: $snapshot->toArray(),
                output: null,
            );
        }

        return $snapshot;
    }

    /**
     * Revierte un soft-delete. No reasigna deleted_by_id/deleted_at (ya
     * quedan en null por el propio restore() de Eloquent) — solo registra
     * quién restauró en updated_by_id, igual que cualquier otro update.
     */
    public function restore(Model $model, ?string $proceso = null): Model
    {
        $pkid = $this->actorPkid();

        $input = $model->toArray();

        if ($pkid !== null) {
            $model->updated_by_id = $pkid;
        }

        $model->restore();

        if ($proceso) {
            $this->audit(
                proceso: $proceso,
                tipo: SiawAuditLog::TIPO_RESTAURACION,
                model: $model,
                input: $input,
                output: $model->toArray(),
            );
        }

        return $model;
    }

    /**
     * Inserta múltiples filas en lotes con DB::table() — no dispara eventos
     * Eloquent ni Observers. Agrega id/created_by_id/timestamps si faltan.
     */
    public function bulkInsert(string $modelClass, array $rows, ?string $proceso = null, int $chunkSize = 500): int
    {
        $pkid  = $this->actorPkid();
        $now   = now();
        $model = new $modelClass;
        $table = DB::connection($model->getConnectionName())->table($model->getTable());
        $total = count($rows);

        $rows = array_map(fn($row) => array_merge($row, [
            'id'            => $row['id'] ?? Str::uuid()->toString(),
            'created_by_id' => $pkid,
            'created_at'    => $row['created_at'] ?? $now,
            'updated_at'    => $row['updated_at'] ?? $now,
        ]), $rows);

        collect($rows)->chunk($chunkSize)->each(fn($lote) => $table->insert($lote->toArray()));

        if ($proceso) {
            SiawAuditLog::create([
                'id'              => Str::uuid()->toString(),
                'nombre_proceso'  => $proceso,
                'tipo_proceso'    => SiawAuditLog::TIPO_INSERCION,
                'estado_proceso'  => SiawAuditLog::ESTADO_EXITO,
                'origen'          => Token::pkid() !== null ? SiawAuditLog::ORIGEN_MANUAL : SiawAuditLog::ORIGEN_AUTOMATICO,
                'modelo_afectado' => class_basename(new $modelClass),
                'registro_id'     => null,
                'input'           => null,
                'output'          => json_encode(['total_insertados' => $total]),
                'error'           => null,
                'created_by_id'   => $pkid,
            ]);
        }

        return $total;
    }

    public function find(string $modelClass, int|string $id): Model
    {
        return $modelClass::findOrFail($id);
    }

    public function getAll(string $modelClass, bool $paginate = true): mixed
    {
        $query = $modelClass::useFilters();
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    /**
     * Convierte campos UUID → PKID en una sola fila. Modifica $data por referencia.
     */
    public function mapUuidsToPkids(array &$data, array $mapping): void
    {
        foreach ($mapping as $field => $modelClass) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = $modelClass::where('id', $data[$field])->value('pkid') ?? 0;
            }
        }
    }

    /**
     * Versión bulk de mapUuidsToPkids: 1 query por modelo para todas las filas.
     */
    public function mapUuidsToPkidsBulk(array &$rows, array $mapping): void
    {
        $uuidsPerModel = array_fill_keys(array_values($mapping), []);

        foreach ($rows as $row) {
            foreach ($mapping as $field => $modelClass) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $uuidsPerModel[$modelClass][] = $row[$field];
                }
            }
        }

        $dictionaries = [];
        foreach ($uuidsPerModel as $modelClass => $uuids) {
            $unique = array_unique($uuids);
            $dictionaries[$modelClass] = !empty($unique)
                ? $modelClass::whereIn('id', $unique)->pluck('pkid', 'id')->toArray()
                : [];
        }

        foreach ($rows as &$row) {
            foreach ($mapping as $field => $modelClass) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = $dictionaries[$modelClass][$row[$field]] ?? 0;
                }
            }
        }
    }

    // ── Internos ─────────────────────────────────────────────────────────

    private function audit(string $proceso, int $tipo, Model $model, mixed $input, mixed $output): void
    {
        $pkid = $this->actorPkid();

        SiawAuditLog::create([
            'id'              => Str::uuid()->toString(),
            'nombre_proceso'  => $proceso,
            'tipo_proceso'    => $tipo,
            'estado_proceso'  => SiawAuditLog::ESTADO_EXITO,
            'origen'          => Token::pkid() !== null ? SiawAuditLog::ORIGEN_MANUAL : SiawAuditLog::ORIGEN_AUTOMATICO,
            'modelo_afectado' => class_basename($model),
            'registro_id'     => $model->id ?? null,
            'input'           => json_encode($input),
            'output'          => json_encode($output),
            'error'           => null,
            'created_by_id'   => $pkid,
        ]);
    }
}
