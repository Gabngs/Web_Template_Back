<?php
namespace App\Exceptions;

/**
 * Se lanza cuando la verificación de un ID Token de Google falla:
 * firma inválida, token expirado, audience o issuer incorrecto, etc.
 */
class GoogleTokenException extends \RuntimeException {}
