<?php
namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse – Respuestas JSON estandarizadas para todos los controllers.
 *
 * Formato estándar:
 *   { "status": "success|error", "message": "...", "data": ... }
 *
 * Uso:
 *   class MiController extends Controller {
 *       use ApiResponse;
 *
 *       public function index() {
 *           return $this->responseSuccess('Lista obtenida', $data);
 *       }
 *   }
 */
trait ApiResponse
{
    protected function responseSuccess(
        string $message,
        mixed $data = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function responseError(
        string $message,
        mixed $errors = null,
        int $status = 422
    ): JsonResponse {
        $body = ['status' => 'error', 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        return response()->json($body, $status);
    }

    protected function responseNotFound(
        string $message = 'Registro no encontrado'
    ): JsonResponse {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
        ], 404);
    }
}
