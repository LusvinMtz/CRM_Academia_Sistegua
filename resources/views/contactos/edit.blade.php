@extends('layouts.app')

@section('title', $contacto->nombre_completo)
@section('content')
    <form method="POST" action="{{ route('contactos.update', [$segmento, $contacto]) }}" novalidate>
        @csrf @method('PUT')
        @include('contactos._form')
    </form>

    @can('eventos.ver')
        <div class="card mt-8">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Historial de participación</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        {{ $historial->whereStrict('asistio', true)->count() }} asistencias de {{ $historial->count() }} convocatorias
                    </span>
                </h3>
            </div>
            <div class="card-body pt-2">
                @if ($historial->isEmpty())
                    <div class="text-muted">Todavía no ha sido convocado a ninguna reunión o capacitación.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-6 gy-3 mb-0">
                            <thead><tr class="text-muted fw-bold fs-7 text-uppercase"><th>Fecha</th><th>Evento</th><th>Respuesta</th><th>Asistencia</th></tr></thead>
                            <tbody class="fw-semibold text-gray-700">
                            @foreach ($historial as $inv)
                                <tr>
                                    <td class="text-nowrap">{{ $inv->evento->inicio->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($inv->evento->tipo), $inv->evento]) }}" class="text-gray-900 text-hover-primary">{{ $inv->evento->titulo }}</a>
                                        @if ($inv->evento->cancelado_at)<span class="badge badge-light-danger ms-1">Anulado</span>@endif
                                    </td>
                                    <td>
                                        @if ($inv->respuesta === 'confirmada')<span class="badge badge-light-success">Confirmó</span>
                                        @elseif ($inv->respuesta === 'rechazada')<span class="badge badge-light-danger">No asistirá</span>
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                    <td>
                                        @if ($inv->asistio === true)<span class="badge badge-success">Asistió</span>
                                        @elseif ($inv->asistio === false)<span class="badge badge-light-danger">Faltó</span>
                                        @else<span class="text-muted">Sin registro</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endcan
@endsection
