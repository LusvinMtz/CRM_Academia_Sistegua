<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Procesa la cola de envíos (correos y WhatsApp) sin depender de una tarea programada ni de una ventana abierta.
 *
 * Al terminar una petición web en la que se encolaron envíos (o, como respaldo, en cualquier visita si hay
 * envíos atrasados), se procesan los pendientes respetando la pausa entre mensajes:
 *  - En el hosting (PHP-FPM / LiteSpeed) se hace en la misma petición, después de entregar la página,
 *    así que el usuario no espera.
 *  - Con "php artisan serve" (que no puede entregar la página antes de terminar) se lanza en segundo plano
 *    el comando envios:procesar.
 * Un candado en caché evita que haya dos procesadores a la vez.
 */
class ProcesadorEnvios
{
    private const CANDADO = 'procesador-envios';
    private const LATIDO = 90;          // segundos sin señal para dar por muerto a un procesador
    private const DURACION_MAXIMA = 1500; // 25 minutos por corrida; lo que quede lo retoma la siguiente visita
    private const ESPERA_MAXIMA = 120;  // si lo siguiente sale después de esto, se deja para otra corrida

    /** Se encolaron envíos en esta petición: hay que procesarlos al terminar. */
    private static bool $hayNuevos = false;

    public static function marcarNuevos(): void
    {
        self::$hayNuevos = true;
    }

    /** Se llama al terminar cada petición web. */
    public static function alTerminarPeticion(): void
    {
        if (config('queue.default') !== 'database') {
            return;
        }
        // Sin envíos nuevos, se revisa a lo sumo cada 30 s si hay atrasados (respaldo si una corrida se cortó)
        if (! self::$hayNuevos && ! Cache::add('procesador-envios:revisado', 1, 30)) {
            return;
        }
        if (Cache::has(self::CANDADO) || self::segundosHastaElSiguiente() === null) {
            return;
        }

        if (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request')) {
            (new self())->procesar(); // La página ya se entregó: esto corre sin que el usuario espere
        } elseif (! self::lanzarEnSegundoPlano()) {
            (new self())->procesar(); // No se pudo lanzar aparte: se procesa aquí mismo
        }
    }

    /**
     * Envía todo lo pendiente, esperando el turno de cada mensaje. Devuelve cuántos trabajos procesó.
     */
    public function procesar(int $duracionMaxima = self::DURACION_MAXIMA): int
    {
        $dueno = (string) Str::uuid();
        if (! Cache::add(self::CANDADO, $dueno, self::LATIDO)) {
            return 0; // Ya hay otro procesando
        }

        ignore_user_abort(true);
        @set_time_limit(0);
        $fin = time() + $duracionMaxima;
        $procesados = 0;

        try {
            while (time() < $fin) {
                Cache::put(self::CANDADO, $dueno, self::LATIDO);

                $espera = self::segundosHastaElSiguiente();
                if ($espera === null || $espera > self::ESPERA_MAXIMA) {
                    break;
                }
                if ($espera > 0) {
                    sleep(min($espera, 30)); // A lo sumo 30 s seguidos, para renovar el latido
                    continue;
                }

                Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 3, '--quiet' => true]);
                $procesados++;
            }
        } finally {
            if (Cache::get(self::CANDADO) === $dueno) {
                Cache::forget(self::CANDADO);
            }
        }

        return $procesados;
    }

    /** Segundos hasta que el siguiente envío pueda salir (0 si ya puede), o null si la cola está vacía. */
    public static function segundosHastaElSiguiente(): ?int
    {
        try {
            $proximo = DB::table(config('queue.connections.database.table', 'jobs'))
                ->where(fn ($q) => $q->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<', now()->subSeconds((int) config('queue.connections.database.retry_after', 90))->getTimestamp()))
                ->min('available_at');
        } catch (Throwable) {
            return null; // Sin tabla (antes de migrar)
        }

        return $proximo === null ? null : max(0, (int) $proximo - time());
    }

    /** Lanza "php artisan envios:procesar" sin esperar a que termine. */
    private static function lanzarEnSegundoPlano(): bool
    {
        $php = (new PhpExecutableFinder())->find(false);
        if (! $php) {
            return false;
        }
        $artisan = base_path('artisan');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                if (! function_exists('popen')) {
                    return false;
                }
                pclose(popen('start "" /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' envios:procesar > NUL 2>&1', 'r'));
            } else {
                if (! function_exists('exec')) {
                    return false;
                }
                exec(escapeshellarg($php).' '.escapeshellarg($artisan).' envios:procesar > /dev/null 2>&1 &');
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
