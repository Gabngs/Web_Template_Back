<?php
namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KeycloakAdminService
 *
 * Interactúa con la Admin REST API de Keycloak usando las credenciales
 * del service account del cliente 'web-backend' (serviceAccountsEnabled: true).
 *
 * Requiere que el service account tenga el rol 'manage-users' del cliente
 * 'realm-management' (ya configurado en keycloak/realm-export.json).
 *
 * Uso principal: establecer contraseña Keycloak para usuarios que se
 * registraron vía Google IDP y quieren tener también acceso directo.
 */
class KeycloakAdminService
{
    // ─── API pública ──────────────────────────────────────────────────────────

    /**
     * Establece la contraseña de un usuario en Keycloak.
     *
     * @param  string $keycloakId  UUID del usuario en Keycloak (sub claim)
     * @param  string $password    Contraseña nueva
     * @return bool                true si se estableció correctamente
     */
    public function setUserPassword(string $keycloakId, string $password): bool
    {
        $adminToken = $this->getAdminToken();

        if (!$adminToken) {
            Log::error('KeycloakAdmin: no se pudo obtener token de administrador');
            return false;
        }

        $url      = config('keycloak.url') . '/admin/realms/' . config('keycloak.realm')
                  . "/users/{$keycloakId}/reset-password";

        $response = Http::withToken($adminToken)
            ->put($url, [
                'type'      => 'password',
                'value'     => $password,
                'temporary' => false,
            ]);

        if (!$response->successful()) {
            Log::error('KeycloakAdmin: error al establecer contraseña', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    // ─── Token de administrador (service account, cacheado) ──────────────────

    private function getAdminToken(): ?string
    {
        return Cache::remember('kc_admin_token', 55, function () {
            $response = Http::asForm()->post(
                config('keycloak.token_uri'),
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => config('keycloak.client_id'),
                    'client_secret' => config('keycloak.client_secret'),
                ]
            );

            if (!$response->ok()) {
                Log::error('KeycloakAdmin: client_credentials grant falló', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }
}
