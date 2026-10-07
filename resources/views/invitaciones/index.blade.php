@extends('layouts.app')

@section('title', 'Invitaciones')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Comunicación</li>
@endsection

@section('content')
    <div class="row g-5 g-xl-8 mb-8">
        @foreach ([
            ['Invitaciones enviadas', $totales['enviadas'], 'ki-send', 'primary'],
            ['Confirmaron asistencia', $totales['confirmadas'], 'ki-check-circle', 'success'],
            ['No asistirán', $totales['rechazadas'], 'ki-cross-circle', 'danger'],
            ['Errores de envío', $totales['fallidas'], 'ki-information', 'warning'],
        ] as [$etq, $n, $icono, $color])
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100"><div class="card-body d-flex align-items-center">
                    <div class="symbol symbol-50px me-5"><span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icono }} fs-2x text-{{ $color }}"></i></span></div>
                    <div>
                        <div class="fs-2hx fw-bold text-gray-900 lh-1">{{ number_format($n) }}</div>
                        <div class="fw-semibold text-gray-600 mt-1">{{ $etq }}</div>
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-gray-900">Historial de envíos</span>
                <span class="text-muted mt-1 fw-semibold fs-7">Las invitaciones se envían desde la ficha de cada reunión o capacitación</span>
            </h3>
            <div class="card-toolbar">
                <form method="GET">
                    <select name="motivo" class="form-select form-select-sm form-select-solid w-auto" onchange="this.form.submit()">
                        <option value="">Todos los tipos</option>
                        @foreach (\App\Models\Envio::MOTIVOS as $valor => $etq)
                            <option value="{{ $valor }}" @selected(request('motivo') === $valor)>{{ $etq }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body py-4">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-muted fw-bold fs-7 text-uppercase">
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th class="min-w-250px">Evento</th>
                        <th class="text-center">Correos</th>
                        <th>Enviado por</th>
                    </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                    @forelse ($envios as $envio)
                        <tr>
                            <td class="text-nowrap">{{ $envio->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge badge-light">{{ \App\Models\Envio::MOTIVOS[$envio->motivo] ?? $envio->motivo }}</span>
                                <i class="ki-outline {{ $envio->canal === 'whatsapp' ? 'ki-whatsapp text-success' : 'ki-sms' }} fs-5 ms-1" title="{{ \App\Models\Invitacion::CANALES[$envio->canal] ?? '' }}"></i>
                            </td>
                            <td>
                                <a href="{{ route('eventos.show', [\App\Models\Evento::segmentoDe($envio->evento->tipo), $envio->evento]) }}#invitaciones"
                                   class="text-gray-900 text-hover-primary fw-bold">{{ $envio->evento->titulo }}</a>
                                <div class="text-muted fs-7">{{ $envio->evento->inicio->format('d/m/Y') }} · Sede {{ $envio->evento->sede->nombre }}</div>
                            </td>
                            <td class="text-center">{{ number_format($envio->total) }}</td>
                            <td>{{ $envio->usuario?->nombre_completo ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-10">
                            Todavía no se han enviado correos. Abra una reunión o capacitación y pulse <strong>Enviar invitaciones</strong>.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $envios->links() }}
        </div>
    </div>
@endsection
