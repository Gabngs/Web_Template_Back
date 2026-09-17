<?php

namespace App\Http\Middleware;

use App\Models\dbsiaw\SiawTokensAcceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSessionKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Token requerido.');
        }

        $bearer = substr($header, 7);

        if (!str_contains($bearer, '@')) {
            return $this->unauthorized('Formato de token inválido. Use: {session_key}@{token}');
        }

        [$sessionKey, $sanctumToken] = explode('@', $bearer, 2);

        // El token de Sanctum tiene formato "{id}|{40chars}"
        if (!str_contains($sanctumToken, '|')) {
            return $this->unauthorized('Formato de token inválido.');
        }

        [$tokenId] = explode('|', $sanctumToken, 2);

        $tokenRecord = SiawTokensAcceso::find($tokenId);

        if (!$tokenRecord || $tokenRecord->session_key !== $sessionKey) {
            return $this->unauthorized('Clave de sesión inválida.');
        }

        // Verificar fingerprint si existe (token fingerprinting)
        if ($tokenRecord->fingerprint !== null) {
            $expected = hash('sha256', $request->userAgent() ?? '');
            if ($tokenRecord->fingerprint !== $expected) {
                return $this->unauthorized('Token usado desde un dispositivo diferente al original.');
            }
        }

        // Reemplazar el header para que auth:sanctum reciba solo el token limpio
        $request->headers->set('Authorization', 'Bearer ' . $sanctumToken);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], 401);
    }
}
