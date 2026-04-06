<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings de servicios se registran en sus propios ServiceProviders
    }

    public function boot(): void
    {
        // Expiración de tokens Sanctum configurable desde .env
        // SANCTUM_TOKEN_EXPIRATION en minutos (default 360 = 6 horas)
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
