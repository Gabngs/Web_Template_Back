<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rota las claves RSA de cifrado de password. El slot dura 2h
// (AuthService::currentSlot() = floor(time()/7200)) pero el comando corre
// cada hora a propósito: auth:rotate-keys genera el slot actual + el
// siguiente, así que un tick perdido o un arranque tardío del scheduler no
// deja a /auth/public-key sin clave en el borde del período. Fijo a UTC
// explícitamente: el cálculo de slot es epoch/UTC puro, independiente de
// APP_TIMEZONE.
Schedule::command('auth:rotate-keys')->hourly()->timezone('UTC');

// §12 — barre cada hora las cotizaciones AGH que superaron su vigencia
// (agh_configuracion_hotel.cotizacion_vigencia_horas; 0 = desactivado) y las
// cancela para liberar la disponibilidad retenida.
Schedule::command('agh:vencer-cotizaciones')->hourly();
