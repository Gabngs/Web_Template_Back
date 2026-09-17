<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Todo Service de módulo debe extender esta clase (ver estándar en el vault:
 * Service Patron/Service del Módulo (Base).md). Fuerza en tiempo de boot que
 * se implementen los métodos correctos con la firma exacta.
 *
 * Nombres en inglés (convención REST de Laravel: index/show/store/update/
 * destroy) — el vault documenta el patrón en español (crear/actualizar/
 * eliminar) solo por simplicidad de lectura, pero indica explícitamente
 * que el código debe usar los nombres estándar en inglés.
 */
abstract class AbstractModuleService
{
    // Cada service DEBE declarar su mapping (puede ser vacío)
    protected array $uuidMapping = [];

    abstract public function index(bool $paginate = false): mixed;
    abstract public function show(Model $model): Model;
    abstract public function store(array $data): Model;
    abstract public function update(Model $model, array $data): Model;
    abstract public function destroy(Model $model): Model;
}
