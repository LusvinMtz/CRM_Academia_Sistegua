@extends('layouts.app')

@php($R = \App\Http\Controllers\ReporteController::class)

@section('title', 'Reportes')

@section('content')
    @include('reportes._pestanas')

    <div class="card mb-8">
        <div class="card-body py-5">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                @if ($contacto)
                    <input type="hidden" name="contacto" value="{{ $contacto->id }}">
                    <div>
                        <label class="form-label fs-7 fw-semibold mb-1">Persona</label>
                        <div class="d-flex align-items-center gap-2 h-35px">
                            <span class="fw-bold text-gray-900">{{ $contacto->nombre_completo }}</span>
                            <a href="{{ route('reportes.historial', request()->only(['desde', 'hasta'])) }}" class="btn btn-sm btn-light py-1 px-3">Ver todas las personas</a>
                        </div>
                    </div>
                @else
                    <div>
                        <label class="form-label fs-7 fw-semibold mb-1">Buscar</label>
                        <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-sm form-control-solid"
                               placeholder="Nombre, DPI, correo o empresa">
                    </div>
                    @if ($sedes->count() > 1)
                        <div>
                            <label class="form-label fs-7 fw-semibold mb-1">Sede</label>
                            <select name="sede" class="form-select form-select-sm form-select-solid">
                                <option value="">Todas</option>
                                @foreach ($sedes as $s)
                                    <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif
                <div>
                    <label class="form-label fs-7 fw-semibold mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $desde?->format('Y-m-d') }}" class="form-control form-control-sm form-control-solid">
                </div>
                <div>
                    <label class="form-label fs-7 fw-semibold mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta?->format('Y-m-d') }}" class="form-control form-control-sm form-control-solid">
                </div>
                <button type="submit" class="btn btn-sm btn-light-primary">Aplicar</button>
                @can('reportes.exportar')
                    <a href="{{ route('reportes.historial.excel', request()->only(['contacto', 'buscar', 'sede', 'desde', 'hasta'])) }}" class="btn btn-sm btn-light ms-auto">
                        <i class="ki-outline ki-file-down fs-3"></i> Exportar a Excel
                    </a>
                @endcan
            </form>
            <div class="text-muted fs-8 mt-2">Sin fechas se muestra todo el historial.</div>
        </div>
    </div>

    @if ($contacto)
        @php($t = $totales)
        <div class="row g-5 g-xl-8 mb-8">
            @foreach ([
                ['Invitaciones', $t['convocatorias'], $t['proximas'] ? '+ '.$t['proximas'].' por realizarse' : 'capacitaciones realizadas', 'ki-sms', 'info'],
                ['Confirmó', $t['confirmadas'], $R::porcentaje($t['confirmadas'], $t['convocatorias']).' de las invitaciones', 'ki-check-circle', 'success'],
                ['Asistió', $t['asistencias'], $R::porcentaje($t['asistencias'], $t['convocatorias']).' de las invitaciones', 'ki-people', 'primary'],
                ['Faltó', $t['ausencias'], $t['confirmo_y_falto'] ? $t['confirmo_y_falto'].' después de confirmar' : null, 'ki-cross-circle', 'danger'],
            ] as [$etq, $n, $detalle, $icono, $color])
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body d-flex align-items-center">
                        <div class="symbol symbol-50px me-5"><span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icono }} fs-2x text-{{ $color }}"></i></span></div>
                        <div>
                            <div class="fs-2hx fw-bold text-gray-900 lh-1">{{ number_format($n) }}</div>
                            <div class="fw-semibold text-gray-700 mt-1">{{ $etq }}</div>
                            @if ($detalle)<div class="fs-7 text-muted">{{ $detalle }}</div>@endif
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">{{ $contacto->nombre_completo }}</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        {{ trim($contacto->empresa.' · '.$contacto->oficio, ' ·') ?: 'Sin empresa' }} · Sede {{ $contacto->sede->nombre }}
                        · {{ $desde ? 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y') : 'Todo el historial' }}
                    </span>
                </h3>
                @can('contactos.editar')
                    <div class="card-toolbar">
                        <a href="{{ route('contactos.edit', [\App\Models\Contacto::segmentoDe($contacto->tipo), $contacto]) }}" class="btn btn-sm btn-light">Ver ficha</a>
                    </div>
                @endcan
            </div>
            <div class="card-body pt-2">
                @include('reportes._historial_tabla', ['invitaciones' => $invitaciones])
                <div class="text-muted fs-8 mt-3">Los totales solo cuentan capacitaciones ya realizadas y no anuladas, igual que el reporte por persona.</div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Capacitaciones por persona</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        {{ $desde ? 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y') : 'Todo el historial' }} · {{ $personas->total() }} personas
                    </span>
                </h3>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-row-dashed border-gray-200 align-middle fs-6 gy-2 gx-3 mb-0 tabla-historial">
                        <thead><tr class="text-muted fw-bold fs-7 text-uppercase">
                            <th class="min-w-225px">Persona</th><th>Fecha</th><th class="min-w-225px">Capacitación</th>
                            <th>Invitación</th><th>Respuesta</th><th>Asistencia</th><th>Resultado</th>
                        </tr></thead>
                        <tbody class="fw-semibold text-gray-700">
                        @forelse ($personas as $c)
                            @php($t = $R::totalesDe($c->invitaciones))
                            @foreach ($c->invitaciones as $i)
                                @php($e = $i->evento)
                                @php([$resp, $respColor] = $R::respuestaTexto($i))
                                @php([$asis, $asisColor] = $R::asistenciaTexto($i))
                                @php([$vs, $vsColor] = $R::versus($i))
                                <tr class="{{ $loop->first ? 'inicio-persona' : '' }}">
                                    @if ($loop->first)
                                        <td rowspan="{{ $c->invitaciones->count() }}" class="align-top bg-light">
                                            <a href="{{ route('reportes.historial', ['contacto' => $c->id] + request()->only(['desde', 'hasta'])) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $c->nombre_completo }}</a>
                                            <div class="fs-7 text-muted">{{ trim($c->empresa.' · '.$c->sede->nombre, ' ·') }}</div>
                                            <div class="fs-7 mt-2">
                                                <span class="text-success fw-bold">{{ $t['asistencias'] }}</span> de {{ $t['convocatorias'] }} asistidas
                                                · <span class="fw-bold">{{ $R::porcentaje($t['asistencias'], $t['convocatorias']) }}</span>
                                            </div>
                                            <div class="fs-8 text-muted">
                                                {{ $t['confirmadas'] }} confirmó · <span class="text-danger">{{ $t['ausencias'] }} faltó</span>
                                            </div>
                                        </td>
                                    @endif
                                    <td class="text-nowrap">{{ $e->inicio->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($e->tipo), $e]) }}" class="text-gray-900 text-hover-primary">{{ $e->titulo }}</a>
                                        @if ($e->cancelado_at)<span class="badge badge-light-danger ms-1">Anulado</span>@endif
                                    </td>
                                    <td class="fs-7">{{ $R::envioTexto($i) }}</td>
                                    <td class="text-{{ $respColor }}">{{ $resp }}</td>
                                    <td class="text-{{ $asisColor }} fw-bold">{{ $asis }}</td>
                                    <td><span class="badge badge-light-{{ $vsColor }} fw-bold">{{ $vs }}</span></td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-10">
                                No hay personas invitadas a capacitaciones{{ request()->filled('buscar') ? ' que coincidan con «'.request('buscar').'»' : '' }}.
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="text-muted fs-8 mt-3">Los totales de cada persona solo cuentan capacitaciones ya realizadas y no anuladas. Haz clic en un nombre para ver su detalle.</div>
                {{ $personas->links() }}
            </div>
        </div>
    @endif
@endsection
