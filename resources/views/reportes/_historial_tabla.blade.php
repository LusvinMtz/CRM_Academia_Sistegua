{{-- Capacitaciones a las que se invitó a una persona: invitación contra respuesta y asistencia. Requiere $invitaciones. --}}
@php($R = \App\Http\Controllers\ReporteController::class)
<div class="table-responsive">
    <table class="table table-row-dashed align-middle fs-6 gy-3 mb-0">
        <thead><tr class="text-muted fw-bold fs-7 text-uppercase">
            <th>Fecha</th><th class="min-w-250px">Capacitación</th><th>Sede</th>
            <th>Invitación</th><th>Respuesta</th><th>Asistencia</th><th>Resultado</th>
        </tr></thead>
        <tbody class="fw-semibold text-gray-700">
        @forelse ($invitaciones as $i)
            @php($e = $i->evento)
            @php([$resp, $respColor] = $R::respuestaTexto($i))
            @php([$asis, $asisColor] = $R::asistenciaTexto($i))
            @php([$vs, $vsColor] = $R::versus($i))
            <tr>
                <td class="text-nowrap">{{ $e->inicio->format('d/m/Y') }}</td>
                <td>
                    <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($e->tipo), $e]) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $e->titulo }}</a>
                    <div class="fs-7 text-muted">
                        {{ $e->es_virtual ? 'Virtual' : 'Presencial' }}
                        @if ($e->cancelado_at)<span class="badge badge-light-danger ms-1">Anulado</span>@endif
                    </div>
                </td>
                <td>{{ $e->sede->nombre }}</td>
                <td class="fs-7">{{ $R::envioTexto($i) }}</td>
                <td class="text-{{ $respColor }}">{{ $resp }}</td>
                <td class="text-{{ $asisColor }} fw-bold">{{ $asis }}</td>
                <td><span class="badge badge-light-{{ $vsColor }} fw-bold">{{ $vs }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">No se le ha invitado a ninguna capacitación en este período.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
