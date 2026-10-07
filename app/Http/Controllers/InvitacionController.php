<?php

namespace App\Http\Controllers;

use App\Mail\CorreoEvento;
use App\Models\ConfiguracionWhatsapp;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Plantilla;
use App\Services\InvitacionesEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Invitaciones de un evento desde el lado del personal: envío, recordatorios,
 * reenvíos, respuestas registradas a mano y vista previa del correo.
 */
class InvitacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:campanias.ver', only: ['index', 'vistaPrevia']),
            new Middleware('permission:campanias.enviar', only: ['enviar', 'recordar', 'reenviar', 'responder']),
        ];
    }

    /** Historial de todos los envíos. */
    public function index(Request $request): View
    {
        $sede = $request->user()->sedeRestringida();

        $envios = Envio::with(['evento.sede', 'usuario'])
            ->whereHas('evento', fn ($q) => $q->when($sede, fn ($w) => $w->where('sede_id', $sede)))
            ->when($request->filled('motivo'), fn ($q) => $q->where('motivo', $request->input('motivo')))
            ->latest()->paginate(20)->withQueryString();

        $invitaciones = Invitacion::whereHas('evento', fn ($q) => $q->when($sede, fn ($w) => $w->where('sede_id', $sede)));

        return view('invitaciones.index', [
            'envios' => $envios,
            'totales' => [
                'enviadas' => (clone $invitaciones)->where('estado_envio', Invitacion::ENVIADA)->count(),
                'confirmadas' => (clone $invitaciones)->where('respuesta', Invitacion::CONFIRMADA)->count(),
                'rechazadas' => (clone $invitaciones)->where('respuesta', Invitacion::RECHAZADA)->count(),
                'fallidas' => (clone $invitaciones)->where('estado_envio', Invitacion::FALLIDA)->count(),
            ],
        ]);
    }

    public function enviar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_unless($evento->acepta_respuestas, 422, 'El evento ya terminó o está cancelado.');
        $datos = $this->validarTexto($request);
        $canal = $this->canal($request);
        if ($error = $this->whatsappNoConfigurado($canal)) {
            return back()->with('error', $error);
        }

        $total = (new InvitacionesEvento($evento))->invitar($datos['asunto'], $datos['mensaje'], $request->user(), $canal);

        return back()->with($total ? 'success' : 'info', $total
            ? "Se enviaron {$total} invitaciones por ".$this->nombreCanal($canal).'.'.$this->notaPrueba($canal)
            : ($canal === Invitacion::WHATSAPP
                ? 'No hay personas nuevas por invitar: todas las que tienen teléfono ya recibieron la invitación por WhatsApp.'
                : 'No hay personas nuevas por invitar: todas las que tienen correo ya fueron invitadas.'));
    }

    public function recordar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_unless($evento->acepta_respuestas, 422, 'El evento ya terminó o está cancelado.');

        $datos = $request->validate([
            'incluir' => ['required', 'array', 'min:1'],
            'incluir.*' => [Rule::in(['sin_respuesta', 'confirmados'])],
            'sin_respuesta.asunto' => ['nullable', 'string', 'max:200'],
            'sin_respuesta.mensaje' => ['nullable', 'string', 'max:5000'],
            'confirmados.asunto' => ['nullable', 'string', 'max:200'],
            'confirmados.mensaje' => ['nullable', 'string', 'max:5000'],
        ], ['incluir.required' => 'Elija al menos un grupo para el recordatorio.']);
        $canal = $this->canal($request);
        if ($error = $this->whatsappNoConfigurado($canal)) {
            return back()->with('error', $error);
        }

        // Cada grupo elegido con su texto (si se dejó vacío, se usa el texto por defecto)
        $textos = [];
        foreach ($datos['incluir'] as $grupo) {
            $defecto = InvitacionesEvento::TEXTOS_AUTOMATICOS[$grupo];
            $textos[$grupo] = [
                'asunto' => $datos[$grupo]['asunto'] ?? null ?: $defecto['asunto'],
                'mensaje' => $datos[$grupo]['mensaje'] ?? null ?: $defecto['mensaje'],
            ];
        }

        [$confirmados, $sinRespuesta] = (new InvitacionesEvento($evento))->recordar($textos, $request->user(), $canal);
        $total = $confirmados + $sinRespuesta;

        $partes = array_filter([
            $sinRespuesta ? "{$sinRespuesta} sin respuesta" : null,
            $confirmados ? "{$confirmados} que confirmaron" : null,
        ]);

        return back()->with($total ? 'success' : 'info', $total
            ? 'Se envió el recordatorio por '.$this->nombreCanal($canal).' a '.implode(' y ', $partes).'.'.$this->notaPrueba($canal)
            : 'No hay personas en los grupos elegidos a quienes recordar por '.$this->nombreCanal($canal).'.');
    }

    public function reenviar(Request $request, string $tipo, Evento $evento, Invitacion $invitacion): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_if($invitacion->evento_id !== $evento->id, 404);

        $canal = $this->canal($request);
        $contacto = $invitacion->contacto;
        if (! $contacto->activo) {
            return back()->with('error', "{$contacto->nombre_completo} está inactivo: no se le envían mensajes.");
        }
        if ($canal === Invitacion::WHATSAPP ? blank($contacto->telefono) : blank($contacto->correo)) {
            return back()->with('error', "{$contacto->nombre_completo} no tiene ".($canal === Invitacion::WHATSAPP ? 'teléfono' : 'correo').' registrado.');
        }
        if ($error = $this->whatsappNoConfigurado($canal)) {
            return back()->with('error', $error);
        }

        (new InvitacionesEvento($evento))->reenviar($invitacion->load('contacto'), $request->user(), $canal);

        return back()->with('success', "Se reenvió la invitación por {$this->nombreCanal($canal)} a {$contacto->nombre_completo}.".$this->notaPrueba($canal));
    }

    /** Registrar la respuesta a mano (por ejemplo, si el padre avisó por teléfono). */
    public function responder(Request $request, string $tipo, Evento $evento, Invitacion $invitacion): RedirectResponse
    {
        $this->autorizar($request, $tipo, $evento);
        abort_if($invitacion->evento_id !== $evento->id, 404);
        $datos = $request->validate(['respuesta' => ['nullable', Rule::in([Invitacion::CONFIRMADA, Invitacion::RECHAZADA])]]);

        $respuesta = $datos['respuesta'] ?? null;
        $invitacion->update([
            'respuesta' => $respuesta,
            'respuesta_por' => $respuesta ? 'personal' : null,
            'respondida_at' => $respuesta ? now() : null,
        ]);

        $nombre = $invitacion->contacto->nombre_completo;

        return back()->with('success', match ($respuesta) {
            Invitacion::CONFIRMADA => "Se registró que {$nombre} asistirá.",
            Invitacion::RECHAZADA => "Se registró que {$nombre} no asistirá.",
            default => "Se borró la respuesta de {$nombre}.",
        });
    }

    /** Muestra cómo se verá el correo, con los datos de la primera persona invitada (o de ejemplo). */
    public function vistaPrevia(Request $request, string $tipo, Evento $evento): string
    {
        $this->autorizar($request, $tipo, $evento);
        $motivo = $request->input('motivo', 'invitacion');
        abort_unless(array_key_exists($motivo, Envio::MOTIVOS), 404);

        $texto = InvitacionesEvento::textoPorDefecto($motivo, $evento);
        $contacto = $evento->destinatarios()->first()
            ?? new Contacto(['nombres' => 'María José', 'apellidos' => 'García López', 'estudiante' => 'Ana Lucía García']);

        $invitacion = new Invitacion(['correo' => $contacto->correo ?? 'correo@ejemplo.com']);
        $invitacion->token = '00000000-0000-0000-0000-000000000000';
        $invitacion->setRelation('evento', $evento->load('sede'));
        $invitacion->setRelation('contacto', $contacto);

        return (new CorreoEvento(
            $invitacion,
            $motivo,
            Plantilla::rellenar($request->input('asunto', $texto['asunto']), $evento, $contacto),
            Plantilla::rellenar($request->input('mensaje', $texto['mensaje']), $evento, $contacto),
        ))->render();
    }

    // ----------------------------------------------------------------------

    private function autorizar(Request $request, string $tipo, Evento $evento): void
    {
        abort_if(! isset(Evento::TIPOS[$tipo]) || Evento::TIPOS[$tipo]['tipo'] !== $evento->tipo, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);
    }

    private function validarTexto(Request $request): array
    {
        return $request->validate([
            'asunto' => ['required', 'string', 'max:200'],
            'mensaje' => ['required', 'string', 'max:5000'],
        ]);
    }

    /** Canal elegido con el botón del formulario: correo (por defecto) o WhatsApp. */
    private function canal(Request $request): string
    {
        return $request->validate(['canal' => ['nullable', Rule::in(array_keys(Invitacion::CANALES))]])['canal'] ?? Invitacion::CORREO;
    }

    private function nombreCanal(string $canal): string
    {
        return $canal === Invitacion::WHATSAPP ? 'WhatsApp' : 'correo';
    }

    private function whatsappNoConfigurado(string $canal): ?string
    {
        $config = ConfiguracionWhatsapp::actual();

        return $canal === Invitacion::WHATSAPP && ! $config->enModoPrueba() && ! $config->tieneToken()
            ? 'Falta configurar el token de WasenderAPI en Administración → WhatsApp.'
            : null;
    }

    private function notaPrueba(string $canal = Invitacion::CORREO): string
    {
        if ($canal === Invitacion::WHATSAPP) {
            return ConfiguracionWhatsapp::actual()->enModoPrueba()
                ? ' (Modo de prueba: los mensajes se guardaron en storage/logs/whatsapp.log y no se enviaron de verdad.)'
                : ' Salen en segundo plano, uno cada pocos segundos para proteger el número.';
        }

        return config('mail.default') === 'log'
            ? ' (Modo de prueba: los correos se guardaron en storage/logs/correos.log y no se enviaron de verdad.)'
            : '';
    }
}
