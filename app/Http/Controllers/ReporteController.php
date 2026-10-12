<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Services\ExcelTabla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de participación: por evento y por persona.
 */
class ReporteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:reportes.ver', only: ['eventos', 'personas', 'historial']),
            new Middleware('permission:reportes.exportar', only: ['eventosExcel', 'personasExcel', 'historialExcel']),
        ];
    }

    public function eventos(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);
        $eventos = $this->consultaEventos($request, $desde, $hasta)->get();

        $porSede = $eventos->groupBy(fn ($e) => $e->sede->nombre)->map(fn ($grupo) => [
            'eventos' => $grupo->count(),
            'invitados' => $grupo->sum('invitados_count'),
            'confirmados' => $grupo->sum('confirmados_count'),
            'asistentes' => $grupo->sum('asistentes_count'),
        ])->sortKeys();

        return view('reportes.eventos', [
            'eventos' => $eventos,
            'porSede' => $porSede,
            'totales' => [
                'eventos' => $eventos->count(),
                'invitados' => $eventos->sum('invitados_count'),
                'confirmados' => $eventos->sum('confirmados_count'),
                'asistentes' => $eventos->sum('asistentes_count'),
            ],
            'desde' => $desde, 'hasta' => $hasta,
            'sedes' => $this->sedes($request),
        ]);
    }

    public function eventosExcel(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
        $filas = $this->consultaEventos($request, $desde, $hasta)->get()->map(fn (Evento $e) => [
            $e->inicio->format('d/m/Y'),
            $e->titulo,
            $e->sede->nombre,
            $e->es_virtual ? 'Virtual' : 'Presencial',
            $e->estado_etiqueta[0],
            (int) $e->invitados_count,
            (int) $e->confirmados_count,
            (int) $e->invitados_count - (int) $e->confirmados_count,
            (int) $e->rechazados_count,
            (int) $e->asistentes_count,
            (int) $e->invitados_count - (int) $e->asistentes_invitados_count,
            self::porcentaje($e->asistentes_count, $e->invitados_count),
        ]);

        return ExcelTabla::descargar(
            'Reporte por evento',
            ['Fecha', 'Capacitación', 'Sede', 'Modalidad', 'Estado', 'Invitaciones enviadas', 'Confirmadas', 'No confirmadas', 'Dijeron que no', 'Asistencias', 'Inasistencias', '% asistencia'],
            $filas,
            'reporte-eventos-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.xlsx',
            config('academia.nombre').' · Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'),
        );
    }

    public function personas(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);
        $personas = $this->consultaPersonas($request, $desde, $hasta)->paginate(25)->withQueryString();

        return view('reportes.personas', [
            'personas' => $personas,
            'desde' => $desde, 'hasta' => $hasta,
            'sedes' => $this->sedes($request),
        ]);
    }

    public function personasExcel(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
        $filas = $this->consultaPersonas($request, $desde, $hasta)->get()->map(fn (Contacto $c) => [
            $c->nombre_completo,
            $c->sede->nombre,
            (string) $c->empresa,
            (string) $c->oficio,
            (string) $c->correo,
            (int) $c->convocatorias_count,
            (int) $c->confirmadas_count,
            (int) $c->asistencias_count,
            (int) $c->ausencias_count,
            self::porcentaje($c->asistencias_count, $c->convocatorias_count),
        ]);

        return ExcelTabla::descargar(
            'Reporte por persona',
            ['Nombre', 'Sede', 'Empresa', 'Oficio', 'Correo', 'Convocatorias', 'Confirmó', 'Asistió', 'Faltó', '% asistencia'],
            $filas,
            'reporte-personas-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.xlsx',
            config('academia.nombre').' · Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'),
        );
    }

    /**
     * Historial de una persona: todas las capacitaciones a las que se le invitó, comparando
     * invitación contra respuesta y asistencia. Sin persona elegida muestra el buscador.
     */
    public function historial(Request $request): View
    {
        [$desde, $hasta] = $this->rangoOpcional($request);
        $contacto = $request->filled('contacto') ? $this->contactoVisible($request) : null;

        // Sin persona elegida: todas las personas invitadas, cada una con sus capacitaciones
        $personas = $contacto ? null : $this->consultaPersonasHistorial($request, $desde, $hasta)->paginate(20)->withQueryString();

        $invitaciones = $contacto ? $this->consultaHistorial($contacto, $desde, $hasta)->get() : collect();

        return view('reportes.historial', [
            'contacto' => $contacto,
            'personas' => $personas,
            'invitaciones' => $invitaciones,
            'totales' => self::totalesDe($invitaciones),
            'desde' => $desde, 'hasta' => $hasta,
            'sedes' => $this->sedes($request),
        ]);
    }

    public function historialExcel(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rangoOpcional($request);
        $contacto = $request->filled('contacto') ? $this->contactoVisible($request) : null;

        $fila = fn (Invitacion $i, Contacto $c) => [
            ...($contacto ? [] : [$c->nombre_completo, (string) $c->empresa]),
            $i->evento->inicio->format('d/m/Y'),
            $i->evento->titulo,
            $i->evento->sede->nombre,
            $i->evento->es_virtual ? 'Virtual' : 'Presencial',
            $i->evento->estado_etiqueta[0],
            self::envioTexto($i),
            self::respuestaTexto($i)[0],
            self::asistenciaTexto($i)[0],
            self::versus($i)[0],
        ];

        $filas = $contacto
            ? $this->consultaHistorial($contacto, $desde, $hasta)->get()->map(fn ($i) => $fila($i, $contacto))
            : $this->consultaPersonasHistorial($request, $desde, $hasta)->get()
                ->flatMap(fn (Contacto $c) => $c->invitaciones->map(fn ($i) => $fila($i, $c)));

        $periodo = $desde ? 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y') : 'Todo el historial';

        return ExcelTabla::descargar(
            $contacto ? 'Historial de '.$contacto->nombre_completo : 'Historial por persona',
            [...($contacto ? [] : ['Persona', 'Empresa']), 'Fecha', 'Capacitación', 'Sede', 'Modalidad', 'Estado', 'Invitación', 'Respuesta', 'Asistencia', 'Resultado'],
            $filas,
            'historial-'.($contacto ? Str::slug($contacto->nombre_completo) : 'personas').'.xlsx',
            config('academia.nombre').' · '.$periodo,
        );
    }

    /** Cómo se le hizo llegar la invitación. */
    public static function envioTexto(Invitacion $i): string
    {
        if ($i->estado_envio === Invitacion::NO_ENVIADA) {
            return 'Sin invitación';
        }
        $canales = array_map(fn ($c) => Invitacion::CANALES[$c], $i->canalesEnviados());

        return match ($i->estado_envio) {
            Invitacion::ENVIADA => 'Enviada'.($canales ? ' ('.implode(' y ', $canales).')' : ''),
            Invitacion::FALLIDA => 'Error de envío',
            default => 'Pendiente de envío',
        };
    }

    /** @return array{0: string, 1: string} texto y color */
    public static function respuestaTexto(Invitacion $i): array
    {
        return match ($i->respuesta) {
            Invitacion::CONFIRMADA => ['Confirmó', 'success'],
            Invitacion::RECHAZADA => ['Dijo que no', 'danger'],
            default => ['Sin respuesta', 'gray-500'],
        };
    }

    /** @return array{0: string, 1: string} texto y color */
    public static function asistenciaTexto(Invitacion $i): array
    {
        return match (true) {
            $i->evento->cancelado_at !== null => ['—', 'gray-500'],
            ! $i->evento->fin->isPast() => ['Pendiente', 'gray-500'],
            $i->asistio === true => ['Asistió', 'success'],
            $i->asistio === false => ['Faltó', 'danger'],
            default => ['Sin registro', 'gray-500'],
        };
    }

    /** Lo que respondió contra lo que hizo. @return array{0: string, 1: string} texto y color */
    public static function versus(Invitacion $i): array
    {
        if ($i->evento->cancelado_at !== null) {
            return ['Capacitación anulada', 'secondary'];
        }
        if (! $i->evento->fin->isPast()) {
            return ['Aún no se realiza', 'primary'];
        }
        if ($i->asistio === null) {
            return ['No se tomó asistencia', 'secondary'];
        }

        return match ([$i->respuesta, $i->asistio]) {
            [Invitacion::CONFIRMADA, true] => ['Confirmó y asistió', 'success'],
            [Invitacion::CONFIRMADA, false] => ['Confirmó y faltó', 'danger'],
            [Invitacion::RECHAZADA, true] => ['Dijo que no, pero asistió', 'info'],
            [Invitacion::RECHAZADA, false] => ['Avisó que no asistiría', 'dark'],
            default => $i->asistio
                ? [$i->estado_envio === Invitacion::NO_ENVIADA ? 'Llegó sin invitación' : 'Asistió sin confirmar', 'info']
                : ['No respondió y faltó', 'warning'],
        };
    }

    public static function porcentaje(int|float|null $parte, int|float|null $total): string
    {
        return $total ? round($parte / $total * 100).'%' : '—';
    }

    // ----------------------------------------------------------------------

    /** Por defecto, el año en curso. */
    private function rango(Request $request): array
    {
        $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date']]);
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : now()->startOfYear();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : now()->endOfYear();

        return $desde->gt($hasta) ? [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()] : [$desde, $hasta];
    }

    /** Como rango(), pero sin fechas abarca todo el historial: [null, null]. */
    private function rangoOpcional(Request $request): array
    {
        $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date']]);
        if (! $request->filled('desde') && ! $request->filled('hasta')) {
            return [null, null];
        }
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : Carbon::create(2000);
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : now()->addYears(10);

        return $desde->gt($hasta) ? [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()] : [$desde, $hasta];
    }

    /** La persona elegida, respetando la sede del usuario. */
    private function contactoVisible(Request $request): Contacto
    {
        return Contacto::with('sede')
            ->when($request->user()->sedeRestringida(), fn ($q, $s) => $q->where('sede_id', $s))
            ->findOrFail($request->integer('contacto'));
    }

    private function consultaHistorial(Contacto $contacto, ?Carbon $desde, ?Carbon $hasta): HasMany
    {
        return $this->filtroHistorial($contacto->invitaciones(), $desde, $hasta);
    }

    /** Invitaciones del período (o todas), de la capacitación más reciente a la más antigua. */
    private function filtroHistorial($invitaciones, ?Carbon $desde, ?Carbon $hasta)
    {
        return $invitaciones
            ->with('evento.sede')
            ->whereHas('evento', fn ($e) => $e->when($desde, fn ($q) => $q->whereBetween('inicio', [$desde, $hasta])))
            ->orderByDesc(Evento::select('inicio')->whereColumn('eventos.id', 'invitaciones.evento_id'));
    }

    /** Todas las personas con al menos una invitación en el período, cada una con su historial cargado. */
    private function consultaPersonasHistorial(Request $request, ?Carbon $desde, ?Carbon $hasta): Builder
    {
        $delPeriodo = fn ($q) => $q->whereHas('evento', fn ($e) => $e->when($desde, fn ($x) => $x->whereBetween('inicio', [$desde, $hasta])));

        return Contacto::with(['sede', 'invitaciones' => fn ($q) => $this->filtroHistorial($q, $desde, $hasta)])
            ->when($this->sedeFiltro($request), fn ($q, $s) => $q->where('sede_id', $s))
            ->buscar($request->input('buscar'))
            ->whereHas('invitaciones', $delPeriodo)
            ->orderBy('apellidos')->orderBy('nombres');
    }

    /** Totales con el mismo criterio del reporte por persona: solo capacitaciones realizadas y no anuladas. */
    public static function totalesDe(iterable $invitaciones): array
    {
        $realizadas = $invitaciones->filter(fn (Invitacion $i) => $i->evento->cancelado_at === null && $i->evento->fin->isPast());

        return [
            'invitaciones' => $invitaciones->count(),
            'convocatorias' => $realizadas->count(),
            'confirmadas' => $realizadas->where('respuesta', Invitacion::CONFIRMADA)->count(),
            'asistencias' => $realizadas->where('asistio', true)->count(),
            'ausencias' => $realizadas->where('asistio', false)->count(),
            'confirmo_y_falto' => $realizadas->where('respuesta', Invitacion::CONFIRMADA)->where('asistio', false)->count(),
            'proximas' => $invitaciones->filter(fn (Invitacion $i) => $i->evento->cancelado_at === null && ! $i->evento->fin->isPast())->count(),
        ];
    }

    private function sedeFiltro(Request $request): ?int
    {
        return $request->user()->sedeRestringida() ?? ($request->filled('sede') ? $request->integer('sede') : null);
    }

    private function sedes(Request $request)
    {
        return Sede::when($request->user()->sedeRestringida(), fn ($q, $s) => $q->whereKey($s))->orderBy('nombre')->get();
    }

    private function consultaEventos(Request $request, Carbon $desde, Carbon $hasta): Builder
    {
        $invitadas = fn ($q) => $q->where('estado_envio', '!=', Invitacion::NO_ENVIADA);

        return Evento::with('sede')
            ->whereBetween('inicio', [$desde, $hasta])
            ->when($this->sedeFiltro($request), fn ($q, $s) => $q->where('sede_id', $s))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when(! $request->boolean('cancelados'), fn ($q) => $q->whereNull('cancelado_at'))
            ->withCount([
                'invitaciones as invitados_count' => $invitadas,
                'invitaciones as confirmados_count' => fn ($q) => $q->where('respuesta', Invitacion::CONFIRMADA),
                'invitaciones as rechazados_count' => fn ($q) => $q->where('respuesta', Invitacion::RECHAZADA),
                'invitaciones as asistentes_count' => fn ($q) => $q->where('asistio', true),
                'invitaciones as asistentes_invitados_count' => fn ($q) => $q->where('estado_envio', Invitacion::ENVIADA)->where('asistio', true),
            ])
            ->orderBy('inicio');
    }

    /** Personas con al menos una convocatoria en el período, ordenadas de menor a mayor asistencia. */
    private function consultaPersonas(Request $request, Carbon $desde, Carbon $hasta): Builder
    {
        $delPeriodo = fn ($q) => $q->whereHas('evento', fn ($e) => $e->whereBetween('inicio', [$desde, $hasta])
            ->whereNull('cancelado_at')->where('fin', '<', now()));

        return Contacto::with('sede')
            ->when($this->sedeFiltro($request), fn ($q, $s) => $q->where('sede_id', $s))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->buscar($request->input('buscar'))
            ->whereHas('invitaciones', $delPeriodo)
            ->withCount([
                'invitaciones as convocatorias_count' => $delPeriodo,
                'invitaciones as confirmadas_count' => fn ($q) => $delPeriodo($q)->where('respuesta', Invitacion::CONFIRMADA),
                'invitaciones as asistencias_count' => fn ($q) => $delPeriodo($q)->where('asistio', true),
                'invitaciones as ausencias_count' => fn ($q) => $delPeriodo($q)->where('asistio', false),
            ])
            ->orderByRaw('asistencias_count / NULLIF(convocatorias_count, 0) asc')
            ->orderBy('apellidos');
    }
}
