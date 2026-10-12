@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    @include('reportes._pestanas')

    <div class="card mb-8">
        <div class="card-body py-5">
            @include('reportes._filtros', [
                'tipos' => ['cliente' => 'Clientes'],
                'rutaExcel' => route('reportes.personas.excel', request()->query()),
                'extra' => new \Illuminate\Support\HtmlString(
                    '<div><label class="form-label fs-7 fw-semibold mb-1">Buscar</label><input type="text" name="buscar" value="'.e(request('buscar')).'" class="form-control form-control-sm form-control-solid" placeholder="Nombre o estudiante"></div>'),
            ])
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-gray-900">Participación por persona</span>
                <span class="text-muted mt-1 fw-semibold fs-7">Eventos ya realizados del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }} · primero quienes menos asistieron</span>
            </h3>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-6 gy-3">
                    <thead><tr class="text-muted fw-bold fs-7 text-uppercase">
                        <th class="min-w-200px">Persona</th><th>Sede</th>
                        <th class="text-end">Convocatorias</th><th class="text-end">Confirmó</th><th class="text-end">Asistió</th><th class="text-end">Faltó</th><th class="w-175px">Asistencia</th>
                    </tr></thead>
                    <tbody class="fw-semibold text-gray-700">
                    @forelse ($personas as $c)
                        @php($p = $c->convocatorias_count ? round($c->asistencias_count / $c->convocatorias_count * 100) : 0)
                        <tr>
                            <td>
                                @can('contactos.editar')
                                    <a href="{{ route('contactos.edit', [\App\Models\Contacto::segmentoDe($c->tipo), $c]) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $c->nombre_completo }}</a>
                                @else
                                    <span class="text-gray-900 fw-bold">{{ $c->nombre_completo }}</span>
                                @endcan
                                <a href="{{ route('reportes.historial', ['contacto' => $c->id]) }}" class="ms-1 text-muted text-hover-primary" title="Ver historial de capacitaciones">
                                    <i class="ki-outline ki-time fs-5"></i>
                                </a>
                                <div class="fs-7 text-muted">
                                    {{ trim($c->empresa.' · '.$c->oficio, ' ·') ?: '—' }}
                                </div>
                            </td>
                            <td>{{ $c->sede->nombre }}</td>
                            <td class="text-end">{{ $c->convocatorias_count }}</td>
                            <td class="text-end">{{ $c->confirmadas_count }}</td>
                            <td class="text-end text-success fw-bold">{{ $c->asistencias_count }}</td>
                            <td class="text-end text-danger">{{ $c->ausencias_count }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="h-6px bg-light rounded flex-grow-1"><div class="bg-{{ $p >= 75 ? 'success' : ($p >= 50 ? 'warning' : 'danger') }} rounded h-6px" style="width: {{ $p }}%"></div></div>
                                    <span class="fs-7 fw-bold">{{ $p }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-10">No hay personas convocadas a eventos realizados en este período.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $personas->links() }}
        </div>
    </div>
@endsection
