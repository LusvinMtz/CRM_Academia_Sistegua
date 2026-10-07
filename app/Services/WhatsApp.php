<?php

namespace App\Services;

use App\Models\ConfiguracionWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de mensajes de WhatsApp con WasenderAPI (https://wasenderapi.com).
 * En modo de prueba los mensajes se escriben en storage/logs/whatsapp.log y no se envían.
 */
class WhatsApp
{
    public const URL = 'https://www.wasenderapi.com/api';

    private readonly ConfiguracionWhatsapp $config;

    public function __construct(?ConfiguracionWhatsapp $config = null)
    {
        $this->config = $config ?? ConfiguracionWhatsapp::actual();
    }

    /**
     * Momento en que puede salir el siguiente mensaje. El turno es común a todo el sistema:
     * si dos personas envían a la vez, sus mensajes se intercalan en vez de salir juntos.
     */
    public static function turno(): Carbon
    {
        $config = ConfiguracionWhatsapp::actual();

        return Cache::lock('whatsapp:turno', 10)->block(10, function () use ($config) {
            // Se guarda como marca de tiempo con microsegundos para no perder precisión entre turnos
            $siguiente = Cache::get('whatsapp:siguiente');
            $turno = $siguiente ? Carbon::createFromTimestamp((float) $siguiente, config('app.timezone')) : null;
            $turno = $turno?->isFuture() ? $turno : now();
            Cache::put('whatsapp:siguiente', (float) $turno->copy()->addSeconds($config->espacioEntreMensajes())->format('U.u'), now()->addDay());

            return $turno;
        });
    }

    /**
     * Número en formato internacional (E.164). Un número de 8 dígitos se toma como nacional
     * y se le antepone el código de país configurado. Devuelve null si no parece un número válido.
     */
    public function numero(?string $telefono): ?string
    {
        if (blank($telefono)) {
            return null;
        }
        $internacional = str_starts_with(trim($telefono), '+') || str_starts_with(trim($telefono), '00');
        $digitos = preg_replace('/\D/', '', $telefono);
        if ($internacional) {
            $digitos = ltrim($digitos, '0');
        } elseif (strlen($digitos) === 8) {
            $digitos = $this->config->codigo_pais.$digitos;
        }

        return strlen($digitos) >= 10 && strlen($digitos) <= 15 ? '+'.$digitos : null;
    }

    public function texto(string $telefono, string $texto): void
    {
        $this->enviar(['to' => $this->destino($telefono), 'text' => $texto]);
    }

    /** El documento debe estar en una dirección pública: WasenderAPI lo descarga de ahí. */
    public function documento(string $telefono, string $url, string $nombreArchivo, ?string $texto = null): void
    {
        $this->enviar(array_filter([
            'to' => $this->destino($telefono), 'documentUrl' => $url, 'fileName' => $nombreArchivo, 'text' => $texto,
        ]));
    }

    /** Estado de la sesión de WhatsApp vinculada al token (connected, need_scan, …). */
    public function estado(): string
    {
        if ($this->config->enModoPrueba()) {
            return 'modo de prueba';
        }

        return (string) ($this->solicitud(fn ($http) => $http->get(self::URL.'/status'))->json('status') ?? 'desconocido');
    }

    private function destino(string $telefono): string
    {
        return $this->numero($telefono) ?? throw new ErrorWhatsApp("El número «{$telefono}» no es válido para WhatsApp.");
    }

    private function enviar(array $datos): void
    {
        if ($this->config->enModoPrueba()) {
            Log::channel('whatsapp')->info('Mensaje de WhatsApp (modo de prueba)', $datos);

            return;
        }

        $this->solicitud(fn ($http) => $http->post(self::URL.'/send-message', $datos));
    }

    private function solicitud(callable $peticion): Response
    {
        if (! $this->config->tieneToken()) {
            throw new ErrorWhatsApp('No se ha configurado el token de WasenderAPI.');
        }

        try {
            $respuesta = $peticion(Http::withToken($this->config->token)->acceptJson()->timeout(30));
        } catch (ConnectionException $e) {
            throw new ErrorWhatsApp('No se pudo conectar con WasenderAPI: '.$e->getMessage());
        }

        if ($respuesta->status() === 429) {
            throw new ErrorWhatsApp(
                'WasenderAPI pidió esperar antes de enviar otro mensaje (límite de envíos).',
                esperar: (int) ($respuesta->json('retry_after') ?? 60),
            );
        }
        if ($respuesta->failed() || $respuesta->json('success') === false) {
            throw new ErrorWhatsApp(match ($respuesta->status()) {
                401, 403 => 'WasenderAPI rechazó el token. Revise que sea el de la sesión de WhatsApp conectada.',
                default => 'WasenderAPI respondió: '.($respuesta->json('message') ?? $respuesta->body()),
            });
        }

        return $respuesta;
    }
}
