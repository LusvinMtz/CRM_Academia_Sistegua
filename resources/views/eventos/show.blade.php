@extends('layouts.app')

@php([$estadoTxt, $estadoColor] = $evento->estado_etiqueta)

@section('title', $evento->titulo)
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('eventos.index', $segmento) }}" class="text-muted text-hover-primary">{{ $config['plural'] }}</a></li>
@endsection

@section('acciones')
    @can('eventos.asistencia')
        @unless ($evento->cancelado_at)
            <a href="{{ route('asistencia.qr', [$segmento, $evento]) }}" class="btn btn-sm btn-light-success">
                <i class="ki-outline ki-scan-barcode fs-2"></i> QR de asistencia
            </a>
            <a href="{{ route('asistencia.show', [$segmento, $evento]) }}" class="btn btn-sm btn-light-success">
                <i class="ki-outline ki-check-square fs-2"></i> Asistencia
            </a>
        @endunless
    @endcan
    <a href="{{ route('eventos.calendario', [$segmento, $evento]) }}" class="btn btn-sm btn-light" data-bs-toggle="tooltip" title="Para Google Calendar, Outlook o el celular">
        <i class="ki-outline ki-calendar-add fs-2"></i> Agregar al calendario
    </a>
    @can('eventos.crear')
        <a href="{{ route('eventos.duplicar', [$segmento, $evento]) }}" class="btn btn-sm btn-light">
            <i class="ki-outline ki-copy fs-2"></i> Duplicar
        </a>
    @endcan
    @can('eventos.editar')
        <a href="{{ route('eventos.edit', [$segmento, $evento]) }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-pencil fs-2"></i> Editar
        </a>
    @endcan
@endsection

@section('content')
    @if ($evento->cancelado_at)
        <div class="alert bg-light-danger border border-danger border-dashed d-flex flex-wrap align-items-center gap-3 p-5 mb-8">
            <i class="ki-outline ki-cross-circle fs-2hx text-danger"></i>
            <div class="flex-grow-1">
                <div class="fw-bold text-gray-900">{{ ucfirst($config['singular']) }} anulada el {{ $evento->cancelado_at->format('d/m/Y') }}</div>
                @if ($evento->motivo_cancelacion)<div class="text-gray-700">Motivo: {{ $evento->motivo_cancelacion }}</div>@endif
            </div>
            @can('eventos.editar')
                <form method="POST" action="{{ route('eventos.reactivar', [$segmento, $evento]) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-light-primary">Reactivar</button>
                </form>
            @endcan
        </div>
    @endif

    <div class="row g-5 g-xl-8">
        <div class="col-xl-8">
            <div class="card mb-5 mb-xl-8">
                <div class="card-body p-lg-10">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-6">
                        <span class="badge badge-light-{{ $estadoColor }} fs-7">{{ $estadoTxt }}</span>
                        <span class="badge badge-light fs-7">{{ $evento->es_virtual ? 'Virtual' : 'Presencial' }}</span>
                        <span class="badge badge-light fs-7">Sede {{ $evento->sede->nombre }}</span>
                    </div>

                    <div class="d-flex flex-column gap-6">
                        <div class="d-flex">
                            <i class="ki-outline ki-calendar fs-2x text-primary me-4"></i>
                            <div>
                                <div class="fw-bold text-gray-900 fs-5">{{ $evento->horario }}</div>
                                <div class="text-muted fs-7">Duración: {{ $evento->duracion_texto }}</div>
                            </div>
                        </div>
                        @if ($evento->es_virtual)
                            <div class="d-flex">
                                <i class="ki-outline ki-screen fs-2x text-primary me-4"></i>
                                <div class="min-w-0">
                                    <div class="fw-bold text-gray-900 fs-5">{{ $evento->plataforma }}</div>
                                    <a href="{{ $evento->enlace }}" target="_blank" rel="noopener" class="fs-7 text-break">{{ $evento->enlace }}</a>
                                </div>
                            </div>
                        @else
                            <div class="d-flex">
                                <i class="ki-outline ki-geolocation fs-2x text-primary me-4"></i>
                                <div>
                                    <div class="fw-bold text-gray-900 fs-5">{{ $evento->lugar }}</div>
                                    <div class="text-muted fs-7">{{ $evento->sede->municipio->nombre }}, {{ $evento->sede->municipio->departamento->nombre }}</div>
                                </div>
                            </div>
                        @endif
                        @if ($evento->facilitador || $evento->cupo)
                            <div class="d-flex">
                                <i class="ki-outline ki-teacher fs-2x text-primary me-4"></i>
                                <div>
                                    @if ($evento->facilitador)<div class="fw-bold text-gray-900 fs-5">{{ $evento->facilitador }}</div>@endif
                                    <div class="text-muted fs-7">{{ $evento->cupo ? "Cupo máximo: {$evento->cupo} participantes" : 'Sin límite de cupo' }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($evento->descripcion)
                        <div class="separator separator-dashed my-8"></div>
                        <h5 class="fw-bold text-gray-800 mb-3">Descripción</h5>
                        <div class="text-gray-700 fs-6" style="white-space: pre-line">{{ $evento->descripcion }}</div>
                    @endif
                </div>
            </div>

            @if ($control['enviadas'] > 0 || $control['asistencia_tomada'])
                @include('eventos._control')
            @endif

            @if ($evento->tipo === 'capacitacion' && $resumen['asistentes'] > 0)
                <div class="card mb-5 mb-xl-8">
                    <div class="card-body d-flex flex-wrap align-items-center gap-4 py-5">
                        <i class="ki-outline ki-award fs-2x text-primary"></i>
                        <div class="flex-grow-1 fw-semibold text-gray-800">{{ $resumen['asistentes'] }} constancias de participación disponibles</div>
                        <a href="{{ route('constancias.todas', $evento) }}" class="btn btn-sm btn-light-primary">Descargar constancias</a>
                    </div>
                </div>
            @endif

            @can('campanias.ver')
                @include('eventos._invitaciones')
            @endcan
        </div>

        <div class="col-xl-4">
            <div class="card mb-5 mb-xl-8">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Dirigida a</h3>
                </div>
                <div class="card-body pt-2">
                    @if ($evento->para_todos)
                        <div class="fw-bold text-gray-900 mb-4">Todos los {{ $config['publico_texto'] }} de la sede {{ $evento->sede->nombre }}</div>
                    @else
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            @foreach ($evento->grupos as $g)
                                <a href="{{ route('grupos.show', $g) }}" class="badge badge-light-primary fs-7 fw-semibold">{{ $g->nombre }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="d-flex flex-stack border border-dashed border-gray-300 rounded p-4 mb-3">
                        <span class="fw-semibold text-gray-700">Personas</span>
                        <span class="fw-bold fs-3 text-gray-900">{{ number_format($totalDestinatarios) }}</span>
                    </div>
                    <div class="d-flex flex-stack border border-dashed border-gray-300 rounded p-4">
                        <span class="fw-semibold text-gray-700">Con correo para invitar</span>
                        <span class="fw-bold fs-3 text-success">{{ number_format($conCorreo) }}</span>
                    </div>
                    <div class="d-flex flex-stack border border-dashed border-gray-300 rounded p-4 mt-3">
                        <span class="fw-semibold text-gray-700">Con teléfono para WhatsApp</span>
                        <span class="fw-bold fs-3 text-success">{{ number_format($conTelefono) }}</span>
                    </div>
                    @if ($totalDestinatarios > $conCorreo)
                        <div class="text-warning fs-7 fw-semibold mt-3">
                            {{ $totalDestinatarios - $conCorreo }} sin correo o que no desean recibir correos.
                        </div>
                    @endif
                    @if ($evento->cupo && $totalDestinatarios > $evento->cupo)
                        <div class="text-warning fs-7 fw-semibold mt-2">Hay más invitados ({{ $totalDestinatarios }}) que cupos ({{ $evento->cupo }}).</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-4">
                        Creada {{ $evento->created_at->format('d/m/Y H:i') }}{{ $evento->autor ? ' por '.$evento->autor->nombre_completo : '' }}.
                    </div>
                    <div class="d-flex flex-column gap-2">
                        @can('eventos.editar')
                            @unless ($evento->cancelado_at)
                                <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#modalPosponer">
                                    <i class="ki-outline ki-calendar-edit fs-3"></i> Posponer {{ $config['singular'] }}
                                </button>
                                <button type="button" class="btn btn-sm btn-light-warning" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                                    <i class="ki-outline ki-cross-circle fs-3"></i> Anular {{ $config['singular'] }}
                                </button>
                            @endunless
                        @endcan
                        @can('eventos.eliminar')
                            @if ($tieneEnviadas)
                                <div class="text-muted fs-7 text-center">Ya se enviaron invitaciones: no se puede eliminar, solo anular o posponer.</div>
                            @else
                                <form method="POST" action="{{ route('eventos.destroy', [$segmento, $evento]) }}"
                                      data-confirmar="¿Eliminar definitivamente &quot;{{ $evento->titulo }}&quot;? Si solo no se realizará, es mejor anularla para conservar el historial.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger w-100"><i class="ki-outline ki-trash fs-3"></i> Eliminar</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('eventos.editar')
        <div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-500px">
                <form method="POST" action="{{ route('eventos.cancelar', [$segmento, $evento]) }}" class="modal-content">
                    @csrf @method('PATCH')
                    <div class="modal-header">
                        <h3 class="modal-title fs-4">Anular {{ $config['singular'] }}</h3>
                        <button type="button" class="btn btn-icon btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"><i class="ki-outline ki-cross fs-1"></i></button>
                    </div>
                    <div class="modal-body">
                        <label for="motivo" class="form-label fw-semibold">Motivo (opcional)</label>
                        <input id="motivo" name="motivo" maxlength="255" class="form-control form-control-solid" placeholder="Por ejemplo: el facilitador no está disponible">
                        <div class="form-text">La {{ $config['singular'] }} queda en el historial como anulada. Si solo cambia la fecha, use <strong>Posponer</strong>.</div>
                        @if ($tieneEnviadas)
                            <div class="bg-light-warning rounded p-4 mt-5 fs-7 fw-semibold text-gray-800">
                                <i class="ki-outline ki-sms fs-3 text-warning me-1"></i>
                                Se enviará un aviso de anulación por correo a {{ $porAvisar }} {{ $porAvisar === 1 ? 'invitado' : 'invitados' }} (incluye el motivo).
                                No se escribe a quienes ya dijeron que no asistirán.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-warning">Anular {{ $config['singular'] }}</button>
                    </div>
                </form>
            </div>
        </div>

        @unless ($evento->cancelado_at)
            <div class="modal fade" id="modalPosponer" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered mw-550px">
                    <form method="POST" action="{{ route('eventos.posponer', [$segmento, $evento]) }}" class="modal-content" novalidate>
                        @csrf @method('PATCH')
                        @php($conError = $errors->posponer->any())
                        <div class="modal-header">
                            <h3 class="modal-title fs-4">Posponer {{ $config['singular'] }}</h3>
                            <button type="button" class="btn btn-icon btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"><i class="ki-outline ki-cross fs-1"></i></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-gray-700 fs-7 mb-5">
                                Fecha actual: <strong>{{ $evento->inicio->translatedFormat('l j \d\e F') }}, de {{ $evento->inicio->format('H:i') }} a {{ $evento->fin->format('H:i') }}</strong>
                            </div>
                            <div class="row g-4">
                                <div class="col-sm-6">
                                    <label for="pos_fecha" class="required form-label fw-semibold">Nueva fecha</label>
                                    <input id="pos_fecha" type="date" name="fecha" min="{{ now()->format('Y-m-d') }}" value="{{ $conError ? old('fecha') : '' }}"
                                           class="form-control form-control-solid @error('fecha', 'posponer') is-invalid @enderror">
                                    @error('fecha', 'posponer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="pos_inicio" class="required form-label fw-semibold">Inicio</label>
                                    <input id="pos_inicio" type="time" name="hora_inicio" value="{{ $conError ? old('hora_inicio') : $evento->inicio->format('H:i') }}"
                                           class="form-control form-control-solid @error('hora_inicio', 'posponer') is-invalid @enderror">
                                    @error('hora_inicio', 'posponer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="pos_fin" class="required form-label fw-semibold">Fin</label>
                                    <input id="pos_fin" type="time" name="hora_fin" value="{{ $conError ? old('hora_fin') : $evento->fin->format('H:i') }}"
                                           class="form-control form-control-solid @error('hora_fin', 'posponer') is-invalid @enderror">
                                    @error('hora_fin', 'posponer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label for="pos_motivo" class="form-label fw-semibold">Motivo (opcional)</label>
                                    <input id="pos_motivo" name="motivo" maxlength="255" value="{{ $conError ? old('motivo') : '' }}"
                                           class="form-control form-control-solid" placeholder="Por ejemplo: por el feriado del 15 de septiembre">
                                </div>
                            </div>
                            @if ($tieneEnviadas)
                                <div class="bg-light-primary rounded p-4 mt-5 fs-7 fw-semibold text-gray-800">
                                    <i class="ki-outline ki-sms fs-3 text-primary me-1"></i>
                                    Se avisará de la nueva fecha por correo a {{ $porAvisar }} {{ $porAvisar === 1 ? 'invitado' : 'invitados' }}, con la fecha anterior y el motivo.
                                    Podrán actualizar su respuesta desde el correo.
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Volver</button>
                            <button type="submit" class="btn btn-primary">{{ $tieneEnviadas ? 'Posponer y avisar' : 'Posponer' }}</button>
                        </div>
                    </form>
                </div>
            </div>
            @if ($errors->posponer->any())
                @push('scripts')
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPosponer')).show();
                        });
                    </script>
                @endpush
            @endif
        @endunless
    @endcan
@endsection
