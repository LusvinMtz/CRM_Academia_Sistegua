<?php

use Illuminate\Support\Facades\Schedule;

// Los envíos en cola (correo y WhatsApp) los procesa el propio sistema al terminar cada petición web
// (App\Services\ProcesadorEnvios), así que no hace falta tarea programada. Si el servidor tiene cron con
// "php artisan schedule:run", esto sirve de refuerzo; el candado del procesador evita que trabajen dos a la vez.
Schedule::command('envios:procesar')
    ->everyMinute()
    ->withoutOverlapping(30);

// Por defecto los recordatorios se envían con el botón "Enviar recordatorio" de cada evento.
// Solo si el sistema se instala en un servidor con tarea programada (php artisan schedule:run cada minuto)
// y se pone RECORDATORIO_AUTOMATICO=true en el .env, se envían también de forma automática el día anterior.
if (config('academia.recordatorio.automatico')) {
    Schedule::command('recordatorios:enviar')
        ->everyFifteenMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/recordatorios.log'));
}
