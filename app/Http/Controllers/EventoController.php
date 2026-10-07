<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionWhatsapp;
use App\Models\Evento;
use App\Models\Grupo;
use App\Models\Invitacion;
use App\Models\Plantilla;
use App\Models\Sede;
use App\Services\InvitacionesEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reuniones con padres de familia y capacitaciones para catedráticos.
 * El segmento {tipo} de la URL ("reuniones" o "capacitaciones") define con cuál se trabaja.
 */
class EventoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:eventos.ver', only: ['index', 'show', 'calendario']),
            new Middleware('permission:eventos.crear', only: ['create', 'store', 'duplicar']),
            new Middleware('permission:eventos.editar', only: ['edit', 'update', 'cancelar', 'posponer', 'reactivar']),
            new Middleware('permission:eventos.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request, string $tipo): View
    {
        $config = $this->config($tipo);
        $estado = $request->input('estado', 'proximos');

        $eventos = $this->consultaBase($request, $config['tipo'])
            ->with(['sede', 'grupos'])
            ->estado($estado)
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($w) => $w
                ->where('titulo', 'like', '%'.$request->input('buscar').'%')
                ->orWhere('facilitador', 'like', '%'.$request->input('buscar').'%')))
            ->when($request->filled('sede'), fn ($q) => $q->where('sede_id', $request->integer('sede')))
            ->when($request->filled('modalidad'), fn ($q) => $q->where('modalidad', $request->input('modalidad')))
            ->orderBy('inicio', $estado === 'proximos' ? 'asc' : 'desc')
            ->paginate(15)->withQueryString();

        return view('eventos.index', [
            'segmento' => $tipo,
            'config' => $config,
            'eventos' => $eventos,
            'estado' => $estado,
            'sedes' => $this->sedesPermitidas($request),
        ]);
    }

    public function create(Request $request, string $tipo): View
    {
        $config = $this->config($tipo);
        $sedes = $this->sedesPermitidas($request);
        $sedeInicial = $request->user()->sedeRestringida() ?? ($sedes->count() === 1 ? $sedes->first()->id : null);

        $evento = new Evento([
            'tipo' => $config['tipo'],
            'modalidad' => Evento::PRESENCIAL,
            'sede_id' => $sedeInicial,
            'lugar' => $sedes->firstWhere('id', $sedeInicial)?->direccion,
        ]);

        return view('eventos.create', $this->datosFormulario($request, $tipo, $evento));
    }

    public function store(Request $request, string $tipo): RedirectResponse
    {
        $config = $this->config($tipo);
        [$datos, $grupos] = $this->validar($request, $config);

        $evento = Evento::create($datos + ['tipo' => $config['tipo'], 'creado_por' => $request->user()->id]);
        $evento->grupos()->sync($grupos);

        return redirect()->route('eventos.show', [$tipo, $evento])
            ->with('success', ucfirst($config['singular'])." \"{$evento->titulo}\" programada.");
    }

    public function show(Request $request, string $tipo, Evento $evento): View
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);
        $evento->load(['sede.municipio.departamento', 'grupos', 'autor', 'envios.usuario']);
        $servicio = new InvitacionesEvento($evento);
        $situacion = $request->input('situacion');

        $invitaciones = $evento->invitaciones()->with('contacto')
            ->where('invitaciones.estado_envio', '!=', Invitacion::NO_ENVIADA)
            ->situacion($situacion)
            ->when($request->filled('buscar'), fn ($q) => $q->whereHas('contacto', fn ($c) => $c->buscar($request->input('buscar'))))
            ->join('contactos', 'contactos.id', '=', 'invitaciones.contacto_id')
            ->orderBy('contactos.apellidos')->orderBy('contactos.nombres')
            ->select('invitaciones.*')
            ->paginate(25, ['*'], 'pagina')->withQueryString();

        $todas = $evento->invitaciones()->where('estado_envio', '!=', Invitacion::NO_ENVIADA);
        $resumen = [
            'invitados' => (clone $todas)->count(),
            'enviadas' => (clone $todas)->where('estado_envio', Invitacion::ENVIADA)->count(),
            'vistas' => (clone $todas)->whereNotNull('vista_at')->count(),
            'confirmadas' => (clone $todas)->where('respuesta', Invitacion::CONFIRMADA)->count(),
            'rechazadas' => (clone $todas)->where('respuesta', Invitacion::RECHAZADA)->count(),
            'sin_respuesta' => (clone $todas)->whereNull('respuesta')->where('estado_envio', Invitacion::ENVIADA)->count(),
            'fallidas' => (clone $todas)->where('estado_envio', Invitacion::FALLIDA)->count(),
            'asistentes' => $evento->invitaciones()->asistentes()->count(),
            'asistencia_tomada' => $evento->invitaciones()->whereNotNull('asistio')->exists(),
        ];

        return view('eventos.show', [
            'segmento' => $tipo,
            'config' => $config,
            'evento' => $evento,
            'totalDestinatarios' => $evento->destinatarios()->count(),
            'conCorreo' => $evento->destinatariosConCorreo()->count(),
            'conTelefono' => $evento->destinatariosConTelefono()->count(),
            'porInvitar' => $servicio->porInvitar()->count(),
            'porInvitarWhatsapp' => $servicio->porInvitar(Invitacion::WHATSAPP)->count(),
            'porRecordar' => $servicio->porRecordar()->count(),
            'porRecordarWhatsapp' => $servicio->porRecordar(Invitacion::WHATSAPP)->count(),
            'confirmadosPorRecordar' => $servicio->confirmadosPorRecordar()->count(),
            'confirmadosPorRecordarWhatsapp' => $servicio->confirmadosPorRecordar(Invitacion::WHATSAPP)->count(),
            'porAvisar' => $servicio->porAvisar()->count(),
            'tieneEnviadas' => $evento->tieneInvitacionesEnviadas(),
            'control' => $evento->control(),
            'invitaciones' => $invitaciones,
            'resumen' => $resumen,
            'situacion' => $situacion,
            'plantillas' => Plantilla::where('tipo_evento', $evento->tipo)->where('activa', true)->orderByDesc('predeterminada')->orderBy('nombre')->get(),
            'textosRecordatorio' => InvitacionesEvento::TEXTOS_AUTOMATICOS,
            'modoPrueba' => config('mail.default') === 'log',
            'modoPruebaWhatsapp' => ConfiguracionWhatsapp::actual()->enModoPrueba(),
            'enviosDetenidos' => ConfiguracionWhatsappController::mensajesDetenidos(),
        ]);
    }

    public function edit(Request $request, string $tipo, Evento $evento): View
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);

        return view('eventos.edit', $this->datosFormulario($request, $tipo, $evento->load('grupos')));
    }

    public function update(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);
        [$datos, $grupos] = $this->validar($request, $config);

        $fechaAnterior = $evento->inicio->copy();
        $evento->fill($datos);
        // Cambios que afectan a los invitados: cuándo y dónde
        $cambioImportante = $evento->isDirty(['inicio', 'fin', 'modalidad', 'lugar', 'enlace']);
        $cambioFecha = ! $evento->inicio->isSameDay($fechaAnterior);
        // Si se movió la fecha, el recordatorio automático se vuelve a programar para la nueva fecha
        if ($evento->isDirty('inicio')) {
            $evento->recordatorio_enviado_at = null;
        }
        $evento->save();
        $evento->grupos()->sync($grupos);

        // Con invitaciones ya enviadas, todo cambio de fecha, hora o lugar se avisa siempre por correo
        $mensaje = 'Se guardaron los cambios.';
        if ($cambioImportante && $evento->acepta_respuestas && $evento->tieneInvitacionesEnviadas()) {
            $servicio = new InvitacionesEvento($evento);
            $avisados = $cambioFecha
                ? $servicio->avisar('posposicion', $request->user(), nota: 'Fecha anterior: '.$this->fechaTexto($fechaAnterior).'.')
                : $servicio->avisar('cambio', $request->user());
            $mensaje .= $avisados ? " Se avisó del cambio por correo a {$avisados} invitados." : '';
        }

        return redirect()->route('eventos.show', [$tipo, $evento])->with('success', $mensaje);
    }

    public function destroy(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);

        if ($evento->tieneInvitacionesEnviadas()) {
            return back()->with('error', "No se puede eliminar porque ya se enviaron invitaciones. Puede anularla o posponerla; en ambos casos se avisa por correo a los invitados.");
        }

        $titulo = $evento->titulo;
        $evento->delete();

        return redirect()->route('eventos.index', $tipo)->with('success', "Se eliminó \"{$titulo}\".");
    }

    public function cancelar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);
        abort_if($evento->cancelado_at !== null, 404);
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        $evento->forceFill(['cancelado_at' => now(), 'motivo_cancelacion' => $datos['motivo'] ?? null])->save();

        // Quien ya tiene la invitación siempre recibe el aviso de anulación
        $mensaje = ucfirst($config['singular']).' anulada. Sigue en el historial.';
        if ($evento->tieneInvitacionesEnviadas()) {
            $avisados = (new InvitacionesEvento($evento))->avisar('cancelacion', $request->user());
            $mensaje .= $avisados ? " Se avisó por correo a {$avisados} invitados." : '';
        }

        return back()->with('success', $mensaje);
    }

    /** Cambia la fecha y avisa a los invitados de la nueva fecha (con la anterior y el motivo). */
    public function posponer(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);
        abort_if($evento->cancelado_at !== null, 404);

        $datos = $request->validateWithBag('posponer', [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
        ], ['hora_inicio' => 'hora de inicio', 'hora_fin' => 'hora de fin']);

        $zona = config('app.timezone');
        $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora_inicio']}", $zona);
        $fin = Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora_fin']}", $zona);

        if ($inicio->isPast()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fecha' => 'La nueva fecha debe ser posterior a hoy.'])
                ->errorBag('posponer');
        }
        if ($inicio->equalTo($evento->inicio) && $fin->equalTo($evento->fin)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fecha' => 'Indique una fecha u hora distinta de la actual.'])
                ->errorBag('posponer');
        }

        $anterior = $evento->inicio->copy();
        // El recordatorio automático se vuelve a programar para la nueva fecha
        $evento->forceFill(['inicio' => $inicio, 'fin' => $fin, 'recordatorio_enviado_at' => null])->save();

        $nota = 'Fecha anterior: '.$this->fechaTexto($anterior).'.'
            .(filled($datos['motivo'] ?? null) ? "\nMotivo: {$datos['motivo']}" : '');
        $avisados = $evento->tieneInvitacionesEnviadas()
            ? (new InvitacionesEvento($evento))->avisar('posposicion', $request->user(), nota: $nota)
            : 0;

        return back()->with('success', ucfirst($config['singular']).' pospuesta para el '.$this->fechaTexto($inicio).'.'
            .($avisados ? " Se avisó de la nueva fecha por correo a {$avisados} invitados." : ''));
    }

    private function fechaTexto(Carbon $fecha): string
    {
        return $fecha->translatedFormat('l j \d\e F \d\e Y').' a las '.$fecha->format('H:i');
    }

    public function reactivar(Request $request, string $tipo, Evento $evento): RedirectResponse
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);

        $evento->forceFill(['cancelado_at' => null, 'motivo_cancelacion' => null])->save();

        return back()->with('success', ucfirst($config['singular']).' reactivada.');
    }

    /** Abre el formulario de un evento nuevo con los datos de uno existente (para reuniones que se repiten). */
    public function duplicar(Request $request, string $tipo, Evento $evento): View
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);

        $copia = $evento->replicate(['cancelado_at', 'motivo_cancelacion', 'creado_por']);
        $copia->inicio = null;
        $copia->fin = null;
        $copia->setRelation('grupos', $evento->grupos);

        return view('eventos.create', $this->datosFormulario($request, $tipo, $copia) + ['duplicado' => $evento]);
    }

    /** Archivo .ics para agregar el evento a Google Calendar, Outlook o el celular. */
    public function calendario(Request $request, string $tipo, Evento $evento): Response
    {
        $config = $this->config($tipo);
        $this->autorizar($request, $evento, $config['tipo']);

        return response(self::ics($evento), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.str($evento->titulo)->slug().'.ics"',
        ]);
    }

    public static function ics(Evento $evento): string
    {
        $esc = fn (?string $t) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $t);
        $utc = fn (Carbon $f) => $f->copy()->utc()->format('Ymd\THis\Z');
        $ubicacion = $evento->es_virtual ? $evento->enlace : $evento->lugar;
        $descripcion = trim($evento->descripcion."\n\n".($evento->es_virtual ? "Enlace: {$evento->enlace}" : "Lugar: {$evento->lugar}"));

        $lineas = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.config('academia.nombre').'//ES', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:evento-'.$evento->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'academia'),
            'DTSTAMP:'.$utc(now()),
            'DTSTART:'.$utc($evento->inicio),
            'DTEND:'.$utc($evento->fin),
            'SUMMARY:'.$esc($evento->titulo),
            'DESCRIPTION:'.$esc($descripcion),
            'LOCATION:'.$esc($ubicacion),
            'STATUS:'.($evento->cancelado_at ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", $lineas)."\r\n";
    }

    // ----------------------------------------------------------------------

    private function config(string $segmento): array
    {
        return Evento::TIPOS[$segmento] ?? abort(404);
    }

    private function consultaBase(Request $request, string $tipo): Builder
    {
        return Evento::tipo($tipo)
            ->when($request->user()->sedeRestringida(), fn ($q, $sede) => $q->where('sede_id', $sede));
    }

    private function sedesPermitidas(Request $request)
    {
        return Sede::activas()
            ->when($request->user()->sedeRestringida(), fn ($q, $sede) => $q->whereKey($sede))
            ->orderBy('nombre')->get();
    }

    /** Grupos activos que se pueden elegir; $incluir agrega los inactivos que el evento ya tenía. */
    private function gruposPermitidos(Request $request, array $config, iterable $incluir = [])
    {
        return Grupo::compatibles($config['publico'], $request->user()->sedeRestringida())->activos($incluir)
            ->with('sede')->withCount('contactos')->orderBy('nombre')->get();
    }

    private function autorizar(Request $request, Evento $evento, string $tipo): void
    {
        abort_if($evento->tipo !== $tipo, 404);
        $sede = $request->user()->sedeRestringida();
        abort_if($sede && $evento->sede_id !== $sede, 403);
    }

    private function datosFormulario(Request $request, string $segmento, Evento $evento): array
    {
        return [
            'segmento' => $segmento,
            'config' => $this->config($segmento),
            'evento' => $evento,
            'sedes' => $this->sedesPermitidas($request),
            'grupos' => $this->gruposPermitidos($request, $this->config($segmento), $evento->grupos->pluck('id')),
        ];
    }

    private function validar(Request $request, array $config): array
    {
        $esCapacitacion = $config['tipo'] === Evento::CAPACITACION;

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'sede_id' => ['required', Rule::in($this->sedesPermitidas($request)->pluck('id'))],
            'modalidad' => ['required', Rule::in([Evento::PRESENCIAL, Evento::VIRTUAL])],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'lugar' => ['required_if:modalidad,'.Evento::PRESENCIAL, 'nullable', 'string', 'max:200'],
            'enlace' => ['required_if:modalidad,'.Evento::VIRTUAL, 'nullable', 'url:https,http', 'max:500'],
            'facilitador' => [$esCapacitacion ? 'nullable' : 'prohibited', 'string', 'max:150'],
            'cupo' => [$esCapacitacion ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:5000'],
            'plantilla_certificado_id' => ['nullable', Rule::exists('plantillas_certificado', 'id')],
            'para_todos' => ['boolean'],
            'recordatorio_automatico' => ['boolean'],
            'grupos' => ['array'],
            'grupos.*' => ['integer'],
        ], [
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'lugar.required_if' => 'Indique el lugar de la actividad presencial.',
            'enlace.required_if' => 'Pegue el enlace de Zoom, Meet o Teams para la actividad virtual.',
            'enlace.url' => 'El enlace debe ser una dirección web completa (que empiece con https://).',
            'sede_id.in' => 'Seleccione una sede válida.',
        ], [
            'sede_id' => 'sede', 'hora_inicio' => 'hora de inicio', 'hora_fin' => 'hora de fin',
            'titulo' => 'título', 'descripcion' => 'descripción',
        ]);

        $paraTodos = $request->boolean('para_todos');
        $grupos = $paraTodos ? [] : Grupo::compatibles($config['publico'], (int) $datos['sede_id'])
            ->whereIn('id', $datos['grupos'] ?? [])->pluck('id')->all();

        if (! $paraTodos && ! $grupos) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'grupos' => "Indique a quién va dirigida: a todos los {$config['publico_texto']} de la sede o a uno o más grupos.",
            ]);
        }

        $zona = config('app.timezone');
        $salida = [
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'sede_id' => (int) $datos['sede_id'],
            'modalidad' => $datos['modalidad'],
            'inicio' => Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora_inicio']}", $zona),
            'fin' => Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora_fin']}", $zona),
            'lugar' => $datos['modalidad'] === Evento::PRESENCIAL ? $datos['lugar'] : null,
            'enlace' => $datos['modalidad'] === Evento::VIRTUAL ? $datos['enlace'] : null,
            'facilitador' => $esCapacitacion ? ($datos['facilitador'] ?? null) : null,
            'cupo' => $esCapacitacion ? ($datos['cupo'] ?? null) : null,
            'plantilla_certificado_id' => $datos['plantilla_certificado_id'] ?? null,
            'para_todos' => $paraTodos,
            'recordatorio_automatico' => $request->boolean('recordatorio_automatico', true),
        ];

        return [$salida, $grupos];
    }
}
