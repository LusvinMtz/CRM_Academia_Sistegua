<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionWhatsapp;
use App\Services\ErrorWhatsApp;
use App\Services\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cuenta de WasenderAPI para enviar invitaciones, recordatorios y constancias por WhatsApp.
 * Usa los mismos permisos que la configuración de correo.
 */
class ConfiguracionWhatsappController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:correo.ver', only: ['edit']),
            new Middleware('permission:correo.editar', only: ['update', 'probar']),
        ];
    }

    public function edit(): View
    {
        $config = ConfiguracionWhatsapp::actual()->loadMissing('actualizadoPor');

        // Estado de la sesión de WhatsApp en WasenderAPI (connected, need_scan, …)
        $sesion = null;
        if (! $config->enModoPrueba() && $config->tieneToken()) {
            try {
                $sesion = (new WhatsApp($config))->estado();
            } catch (ErrorWhatsApp $e) {
                $sesion = 'error: '.$e->getMessage();
            }
        }

        return view('whatsapp.edit', [
            'config' => $config,
            'sesion' => $sesion,
            'colaSincrona' => config('queue.default') === 'sync',
            'enEspera' => self::mensajesDetenidos(),
        ]);
    }

    /** Envíos que ya debieron salir hace más de 3 minutos: señal de que el procesador de envíos no está corriendo. */
    public static function mensajesDetenidos(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(3)->getTimestamp())->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $config = ConfiguracionWhatsapp::actual();
        $real = $request->input('modo') === ConfiguracionWhatsapp::API;

        $datos = $request->validate([
            'modo' => ['required', Rule::in(array_keys(ConfiguracionWhatsapp::MODOS))],
            'token' => [Rule::requiredIf($real && ! $config->tieneToken()), 'nullable', 'string', 'max:255'],
            'codigo_pais' => ['required', 'regex:/^[1-9][0-9]{0,3}$/'],
            'pausa_segundos' => ['required', 'integer', 'between:'.($request->boolean('proteccion') ? ConfiguracionWhatsapp::PAUSA_MINIMA_PROTECCION : 0).',600'],
        ], [
            'codigo_pais.regex' => 'El código de país son solo números, sin el signo + (Guatemala: 502).',
            'pausa_segundos.between' => 'La pausa debe estar entre :min y :max segundos'
                .($request->boolean('proteccion') ? ' (con la protección activa, al menos 5).' : '.'),
        ], [
            'token' => 'token de acceso',
            'codigo_pais' => 'código de país',
            'pausa_segundos' => 'pausa entre mensajes',
        ]);

        if (blank($datos['token'])) {
            unset($datos['token']); // En blanco: se conserva el guardado
        } else {
            $datos['token'] = trim($datos['token']);
        }

        $config->fill($datos + ['proteccion' => $request->boolean('proteccion'), 'actualizado_por' => $request->user()->id])->save();

        return redirect()->route('whatsapp.edit')->with('success', 'Se guardó la configuración de WhatsApp. '
            .($config->enModoPrueba() ? 'Los mensajes no se enviarán mientras esté en modo de prueba.' : 'Envíe un mensaje de prueba para confirmar que funciona.'));
    }

    public function probar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['destino' => ['required', 'string', 'max:20']], [], ['destino' => 'número de destino']);

        $config = ConfiguracionWhatsapp::actual();
        $whatsapp = new WhatsApp($config);
        if (! $whatsapp->numero($datos['destino'])) {
            return back()->withInput()->withErrors(['destino' => 'Escriba un número de 8 dígitos o uno internacional con +.']);
        }

        try {
            $whatsapp->texto($datos['destino'],
                '*'.config('academia.nombre')."*\n\nEste es un mensaje de prueba. Si lo está leyendo, el envío por WhatsApp funciona correctamente.\n\n"
                .'Enviado el '.now()->format('d/m/Y H:i').' por '.$request->user()->name.'.'
            );
        } catch (ErrorWhatsApp $e) {
            return back()->withInput()->with('error', 'No se pudo enviar el mensaje de prueba: '.$e->getMessage());
        }

        return back()->with('success', $config->enModoPrueba()
            ? 'Modo de prueba: el mensaje quedó en storage/logs/whatsapp.log y no se envió.'
            : 'Se envió el mensaje de prueba a '.$whatsapp->numero($datos['destino']).'.');
    }
}
