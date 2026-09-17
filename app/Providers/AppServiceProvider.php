<?php

namespace App\Providers;

use App\Database\CentralMigrationRepository;
use App\Models\dbsiaw\SiawTokensAcceso;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ledger de migraciones unificado en la conexión dbsincro.
        $this->app->extend('migration.repository', function ($repository, $app) {
            $migrations = $app['config']['database.migrations'];
            $table = is_array($migrations) ? ($migrations['table'] ?? 'migrations') : $migrations;

            return new CentralMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SiawUsuarios::tokens() (de HasApiTokens) usa este modelo en vez del
        // Laravel\Sanctum\PersonalAccessToken por defecto — sin esto, Sanctum
        // busca la tabla estándar "personal_access_tokens" (que no existe en
        // este proyecto) en vez de "siaw_tokens_acceso".
        Sanctum::usePersonalAccessTokenModel(SiawTokensAcceso::class);

        View::composer('emails.*', function ($view) {
            $nombre = config('app.name');
            $view->with('marca', $nombre && $nombre !== 'Laravel' ? $nombre : 'LCS Backend');
        });

        // Cada subcarpeta de database/migrations/ es un módulo con su propia conexión.
        $this->loadMigrationsFrom(glob(database_path('migrations/*'), GLOB_ONLYDIR));

        // Login: máx 5 intentos por minuto por IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Demasiados intentos. Espere 1 minuto.',
                ], 429));
        });

        // APIs generales: límite por TOKEN de acceso, no por IP. Cada token
        // tiene su propio presupuesto, así varios usuarios detrás de una misma
        // IP (oficina / NAT) no se comen el cupo entre ellos. Si la request no
        // está autenticada (llega a una ruta protegida sin token válido) se cae
        // a un límite por IP más bajo — igual va a terminar en 401.
        RateLimiter::for('api', function (Request $request) {
            $tokenId = $request->user()?->currentAccessToken()?->getKey();

            $limit = $tokenId
                ? Limit::perMinute(120)->by('tok_'.$tokenId)
                : Limit::perMinute(30)->by('ip_'.$request->ip());

            return $limit->response(fn() => response()->json([
                'status'  => 'error',
                'message' => 'Demasiadas peticiones. Baje el ritmo e intente de nuevo en unos segundos.',
            ], 429));
        });
    }
}
