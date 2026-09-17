<?php

namespace App\Console\Commands\Siaw;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill único e idempotente del sistema en el catálogo de permisos:
 *
 *  1. siaw_content_model.sistema_id  <- inferido de app_label vía
 *     config('sistemas.app_label_map'). Solo toca filas con sistema_id NULL.
 *  2. siaw_content_permisos.sistema_id <- copiado desde su content model.
 *
 * Se puede correr las veces que haga falta (p. ej. tras dar de alta un sistema
 * nuevo). Nunca pisa un sistema_id ya asignado.
 */
class PoblarSistemaIdContentPermisos extends Command
{
    protected $signature = 'siaw:poblar-sistema-id {--dry-run : Muestra los cambios sin escribir nada}';

    protected $description = 'Rellena siaw_content_model.sistema_id (desde app_label) y lo propaga a siaw_content_permisos';

    private const CONNECTION = 'dbsiaw';

    public function handle(): int
    {
        $mapa   = config('sistemas.app_label_map', []);
        $dryRun = (bool) $this->option('dry-run');
        $db     = DB::connection(self::CONNECTION);

        if (empty($mapa)) {
            $this->error('config/sistemas.php -> app_label_map está vacío. Nada que hacer.');
            return self::FAILURE;
        }

        $sistemas = $db->table('siaw_sistemas')->whereNull('deleted_at')->pluck('pkid', 'codigo');

        // ── 1. content_model.sistema_id desde app_label ──────────────────────
        $totalModelos = 0;
        foreach ($mapa as $appLabel => $codigo) {
            $sistemaPkid = $sistemas[$codigo] ?? null;

            if ($sistemaPkid === null) {
                $this->warn("codigo '{$codigo}' no existe en siaw_sistemas — se omite app_label '{$appLabel}'.");
                continue;
            }

            $filtro = fn() => $db->table('siaw_content_model')
                ->whereRaw('LOWER(app_label) = ?', [strtolower((string) $appLabel)])
                ->whereNull('sistema_id')
                ->whereNull('deleted_at');

            $afectados = $filtro()->count();

            if (!$dryRun && $afectados > 0) {
                $filtro()->update(['sistema_id' => $sistemaPkid, 'updated_at' => now()]);
            }

            $this->line(sprintf('  %-8s -> %-6s : %d content model(s)', $appLabel, $codigo, $afectados));
            $totalModelos += $afectados;
        }

        // ── 2. Propagar a los permisos sin sistema ───────────────────────────
        $permisoFiltro = fn() => $db->table('siaw_content_permisos as p')
            ->join('siaw_content_model as cm', 'cm.pkid', '=', 'p.content_model_id')
            ->whereNull('p.sistema_id')
            ->whereNotNull('cm.sistema_id')
            ->whereNull('p.deleted_at');

        $permisosAfectados = $permisoFiltro()->count();

        if (!$dryRun && $permisosAfectados > 0) {
            $permisoFiltro()->update([
                'p.sistema_id' => DB::raw('cm.sistema_id'),
                'p.updated_at' => now(),
            ]);
        }

        // ── Resumen ─────────────────────────────────────────────────────────
        $modelosSinSistema  = $db->table('siaw_content_model')->whereNull('sistema_id')->whereNull('deleted_at')->count();
        $permisosSinSistema = $db->table('siaw_content_permisos')->whereNull('sistema_id')->whereNull('deleted_at')->count();

        $this->info(sprintf(
            '%s%d content model(s) y %d permiso(s) %s.',
            $dryRun ? '[dry-run] ' : '',
            $totalModelos,
            $permisosAfectados,
            $dryRun ? 'se actualizarían' : 'actualizados',
        ));
        $this->line("  Quedan sin sistema: {$modelosSinSistema} content model(s), {$permisosSinSistema} permiso(s) (esperado para app_label 'siaw').");

        return self::SUCCESS;
    }
}
