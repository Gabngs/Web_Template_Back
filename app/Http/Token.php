<?php

namespace App\Http;

use App\Models\dbsiaw\SiawUsuarios;

/**
 * Acceso estático al usuario autenticado por Sanctum.
 * Centraliza el guard para que CrudService y los servicios de negocio
 * no dependan directamente de auth() ni del nombre del guard.
 *
 * OJO: el guard `sanctum` puede resolver "tokenables" que NO son SiawUsuarios
 * ni siquiera Authenticatable (p. ej. un modelo de negocio con acceso público
 * vía token: Model + HasApiTokens, nada más). Por eso `user()`
 * filtra por tipo y devuelve null en esos casos — así `pkid()`/`id()` no
 * explotan y CrudService cae a su usuario de auditoría por defecto. Para el
 * tokenable crudo, sea del tipo que sea, usar `sanctumUser()` (retorno `mixed`
 * a propósito: puede ser cualquier modelo con HasApiTokens, o null).
 */
class Token
{
    public static function user(): ?SiawUsuarios
    {
        $user = auth('sanctum')->user();

        return $user instanceof SiawUsuarios ? $user : null;
    }

    /** El "tokenable" crudo de Sanctum (SiawUsuarios, colaborador de almuerzos, …). */
    public static function sanctumUser(): mixed
    {
        return auth('sanctum')->user();
    }

    public static function pkid(): ?int
    {
        return static::user()?->pkid;
    }

    public static function id(): ?string
    {
        return static::user()?->id;
    }
}
