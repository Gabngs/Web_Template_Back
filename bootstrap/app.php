<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API pura, sin rutas web de login. Por defecto Laravel intenta
        // redirigir a route('login') cuando el auth falla y el request no
        // pide JSON explícitamente (ej: Accept: */*) — como esa ruta no
        // existe aquí, eso tira un 500 en vez de un 401 limpio.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Laravel reordena el middleware por prioridad interna: auth:sanctum
        // tiene prioridad alta por defecto y se ejecuta ANTES que cualquier
        // middleware custom sin prioridad declarada, sin importar el orden
        // en que se registró en la ruta. Sin esto, ValidateSessionKey nunca
        // corre antes que auth:sanctum, que recibe el header crudo
        // "{session_key}@{id}|{token}" y lo rechaza por formato inválido —
        // siempre 401 "Unauthenticated." aunque el token sea válido.
        //
        // OJO: la lista de prioridad de Laravel usa la INTERFAZ
        // AuthenticatesRequests, no la clase concreta Authenticate — apuntar
        // a la clase concreta no encuentra coincidencia y el middleware
        // termina al final de la lista en vez de antes de la autenticación.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\ValidateSessionKey::class,
        );

        $middleware->alias([
            'session.key'      => \App\Http\Middleware\ValidateSessionKey::class,
            'forzar.cambio'    => \App\Http\Middleware\ForzarCambioPassword::class,
            'permiso'          => \App\Http\Middleware\RequierePermiso::class,
            'swagger.enabled'  => \App\Http\Middleware\EnsureSwaggerEnabled::class,
            'dev.only'         => \App\Http\Middleware\EnsureDevEndpointsEnabled::class,

            // Abilities de token Sanctum — las usa el módulo Almuerzos
            // (almuerzo:enviar / almuerzo:asignar / almuerzo:editar). No vienen
            // registradas por defecto en Laravel 11+.
            'abilities'        => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'          => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API pura, sin vistas HTML — el Handler decide JSON vs redirect según
        // el header Accept (shouldReturnJson), y si el cliente manda
        // "Accept: */*" (curl, Postman, Swagger por defecto) cae al branch de
        // redirect()->guest(route('login')), que no existe aquí. Forzamos que
        // TODA excepción se responda como JSON sin importar el Accept.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
