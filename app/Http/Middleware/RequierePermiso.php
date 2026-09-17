<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequierePermiso
{
    /**
     * Uso en rutas — dos modos, distinguidos por el prefijo `can_`:
     *
     * 1) Slugs de rol (legacy, sin cambios — lo sigue usando Siaw):
     *      ->middleware('permiso:isSuperUser')
     *      ->middleware('permiso:isAdmin,isSuperUser')  (cualquiera de los dos)
     *
     * 2) Codenames del catálogo siaw_content_permisos (Agh/Nexo):
     *      ->middleware('permiso:can_create_habitaciones')
     *      ->middleware('permiso:can_update_x,can_admin_y')  (cualquiera de los dos)
     *    Resuelve contra siaw_permiso_rol (por los roles del usuario) con
     *    override explícito por usuario en siaw_permiso_usuario.permitido
     *    (0 = denegado aunque el rol lo otorgue, 1 = otorgado aunque el rol
     *    no lo tenga). isSuperUser siempre pasa cualquier codename — nunca
     *    se puede quedar bloqueado por un permiso mal asignado o sin sembrar.
     *
     * Antes de esta clase, todas las rutas de escritura de Agh/Nexo usaban el
     * modo 1 con isSuperUser,isAdmin — un gate de rol, no de acción concreta,
     * pese a que el catálogo can_{accion}_{modulo} ya existía como data desde
     * los seeders (ver AghModulosAdminSeeder). Ese catálogo pasa a ser real acá.
     */
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $usuario = $request->user();

        if (!$usuario) {
            return $this->forbidden('No autenticado.');
        }

        $codenames = array_values(array_filter($args, fn($a) => str_starts_with($a, 'can_')));
        $slugs     = array_values(array_diff($args, $codenames));

        if ($slugs && $this->tieneAlgunRol($usuario->pkid, $slugs)) {
            return $next($request);
        }

        if ($codenames && $this->tieneAlgunCodename($usuario->pkid, $codenames)) {
            return $next($request);
        }

        return $this->forbidden('No tiene permiso para realizar esta acción.');
    }

    private function tieneAlgunRol(int $usuarioPkid, array $slugs): bool
    {
        return DB::connection('dbsiaw')
            ->table('siaw_rol_usuario as ru')
            ->join('siaw_roles as r', 'r.pkid', '=', 'ru.rol_id')
            ->where('ru.usuario_id', $usuarioPkid)
            ->whereIn('r.slug', $slugs)
            ->whereNull('r.deleted_at')
            ->exists();
    }

    private function tieneAlgunCodename(int $usuarioPkid, array $codenames): bool
    {
        $db = DB::connection('dbsiaw');

        if ($this->tieneAlgunRol($usuarioPkid, ['isSuperUser'])) {
            return true;
        }

        $permisoIds = $db->table('siaw_content_permisos')->whereIn('codename', $codenames)->pluck('pkid');
        if ($permisoIds->isEmpty()) {
            return false;
        }

        foreach ($permisoIds as $permisoId) {
            $override = $db->table('siaw_permiso_usuario')
                ->where('usuario_id', $usuarioPkid)
                ->where('permiso_id', $permisoId)
                ->whereNull('deleted_at')
                ->value('permitido');

            if ($override !== null) {
                if ((bool) $override) {
                    return true;
                }
                continue; // denegado explícitamente para este codename — probar el siguiente de la lista
            }

            $porRol = $db->table('siaw_rol_usuario as ru')
                ->join('siaw_permiso_rol as pr', 'pr.rol_id', '=', 'ru.rol_id')
                ->where('ru.usuario_id', $usuarioPkid)
                ->where('pr.permiso_id', $permisoId)
                ->exists();

            if ($porRol) {
                return true;
            }
        }

        return false;
    }

    private function forbidden(string $message): Response
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], 403);
    }
}
