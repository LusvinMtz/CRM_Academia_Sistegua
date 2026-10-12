@extends('layouts.publico')

@php
    $contacto = $invitacion->contacto;
    $respuesta = $invitacion->respuesta;
    $abierto = $evento->acepta_respuestas;
    $sinCupo = $abierto && $evento->cupo_lleno && $respuesta !== \App\Models\Invitacion::CONFIRMADA;
@endphp

@section('title', $evento->titulo)
@section('sede', 'Sede '.$evento->sede->nombre)

@section('content')
    @if (session('respondido'))
        <div class="alert bg-light-{{ session('respondido') === 'confirmada' ? 'success' : 'primary' }} border border-{{ session('respondido') === 'confirmada' ? 'success' : 'primary' }} border-dashed d-flex align-items-center p-5 mb-6">
            <i class="ki-outline {{ session('respondido') === 'confirmada' ? 'ki-check-circle text-success' : 'ki-information-5 text-primary' }} fs-2hx me-4"></i>
            <div class="fw-semibold text-gray-800 fs-6">
                {{ session('respondido') === 'confirmada' ? '¡Gracias! Registramos su asistencia.' : 'Gracias por avisarnos. Registramos que no podrá asistir.' }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="alert bg-light-danger border border-danger border-dashed p-5 mb-6 fw-semibold text-gray-800">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm mb-6">
        <div class="card-body p-6 p-md-10">
            <div class="text-gray-600 fs-6 mb-2">Hola, <strong class="text-gray-900">{{ $contacto->nombre_completo }}</strong></div>
            <span class="badge badge-light-primary fs-8 fw-bold text-uppercase mb-3">
                {{ $evento->tipo === 'reunion' ? 'Reunión' : 'Capacitación' }} · {{ $evento->es_virtual ? 'Virtual' : 'Presencial' }}
            </span>
            <h1 class="fs-2 fw-bold text-gray-900 mb-6 {{ $evento->cancelado_at ? 'text-decoration-line-through' : '' }}">{{ $evento->titulo }}</h1>

            <div class="d-flex flex-column gap-5 mb-6">
                <div class="d-flex">
                    <i class="ki-outline ki-calendar fs-2x text-primary me-4"></i>
                    <div class="fw-bold text-gray-900 fs-6">{{ $evento->horario }}</div>
                </div>
                @if ($evento->es_virtual)
                    <div class="d-flex">
                        <i class="ki-outline ki-screen fs-2x text-primary me-4"></i>
                        <div class="min-w-0">
                            <div class="fw-bold text-gray-900 fs-6">{{ $evento->plataforma }}</div>
                            @if (! $evento->cancelado_at)
                                <a href="{{ $evento->enlace }}" target="_blank" rel="noopener" class="fs-7 text-break">{{ $evento->enlace }}</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="d-flex">
                        <i class="ki-outline ki-geolocation fs-2x text-primary me-4"></i>
                        <div>
                            <div class="fw-bold text-gray-900 fs-6">{{ $evento->lugar }}</div>
                            <div class="text-muted fs-7">{{ $evento->sede->municipio->nombre }}, {{ $evento->sede->municipio->departamento->nombre }}</div>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($evento->lugar.', '.$evento->sede->municipio->nombre.', Guatemala') }}"
                               target="_blank" rel="noopener" class="fs-7">Ver en el mapa</a>
                        </div>
                    </div>
                @endif
                @if ($evento->facilitador)
                    <div class="d-flex">
                        <i class="ki-outline ki-teacher fs-2x text-primary me-4"></i>
                        <div class="fw-semibold text-gray-800 fs-6">{{ $evento->facilitador }}</div>
                    </div>
                @endif
            </div>

            @if ($evento->descripcion)
                <div class="text-gray-700 fs-6 mb-2" style="white-space: pre-line">{{ $evento->descripcion }}</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-6">
        <div class="card-body p-6 p-md-10">
            @if ($evento->cancelado_at)
                <div class="d-flex align-items-center">
                    <i class="ki-outline ki-cross-circle fs-2hx text-danger me-4"></i>
                    <div>
                        <div class="fw-bold text-gray-900 fs-5">Esta actividad fue cancelada</div>
                        @if ($evento->motivo_cancelacion)<div class="text-gray-700">{{ $evento->motivo_cancelacion }}</div>@endif
                    </div>
                </div>
            @elseif (! $abierto)
                <div class="fw-bold text-gray-800 fs-5">Esta actividad ya se realizó.</div>
                @if ($respuesta)
                    <div class="text-muted mt-2">Su respuesta fue: {{ $respuesta === 'confirmada' ? 'asistiré' : 'no podré asistir' }}.</div>
                @endif
            @elseif ($respuesta)
                @php($confirmo = $respuesta === 'confirmada')
                <div class="d-flex align-items-center mb-6">
                    <i class="ki-outline {{ $confirmo ? 'ki-check-circle text-success' : 'ki-cross-circle text-danger' }} fs-3x me-4"></i>
                    <div>
                        <div class="text-gray-600 fs-7">Su respuesta</div>
                        <div class="fs-4 fw-bold text-{{ $confirmo ? 'success' : 'danger' }}">{{ $confirmo ? 'Asistiré' : 'No podré asistir' }}</div>
                        @if ($invitacion->comentario)
                            <div class="text-gray-700 fs-7 mt-1">"{{ $invitacion->comentario }}"</div>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('invitacion.responder', $invitacion->token) }}" class="d-flex flex-wrap align-items-center gap-3">
                    @csrf
                    @if ($canal)<input type="hidden" name="c" value="{{ $canal }}">@endif
                    <input type="hidden" name="comentario" value="{{ $invitacion->comentario }}">
                    <span class="text-gray-600 fs-7">¿Cambió de planes?</span>
                    @if ($confirmo)
                        <button type="submit" name="respuesta" value="rechazada" class="btn btn-sm btn-light-danger">
                            <i class="ki-outline ki-cross fs-3"></i> Ya no podré asistir
                        </button>
                    @elseif ($sinCupo)
                        <span class="badge badge-light-warning fs-7">Ya no hay cupos disponibles para confirmar.</span>
                    @else
                        <button type="submit" name="respuesta" value="confirmada" class="btn btn-sm btn-light-success">
                            <i class="ki-outline ki-check fs-3"></i> Sí podré asistir
                        </button>
                    @endif
                </form>

                @if ($confirmo)
                    <div class="separator separator-dashed my-6"></div>
                    <a href="{{ route('invitacion.calendario', $invitacion->token) }}" class="btn btn-sm btn-light-primary">
                        <i class="ki-outline ki-calendar-add fs-3"></i> Agregar a mi calendario
                    </a>
                @endif
            @else
                <h2 class="fs-4 fw-bold text-gray-900 mb-2">¿Podrá asistir?</h2>
                <div class="text-gray-600 mb-5">Por favor, indíquenos su respuesta.</div>

                <form method="POST" action="{{ route('invitacion.responder', $invitacion->token) }}">
                    @csrf
                    @if ($canal)<input type="hidden" name="c" value="{{ $canal }}">@endif
                    <div class="mb-5">
                        <label for="comentario" class="form-label fw-semibold text-gray-700 fs-7">Comentario (opcional)</label>
                        <textarea id="comentario" name="comentario" rows="2" maxlength="500" class="form-control form-control-solid"
                                  placeholder="Por ejemplo: llegaré unos minutos tarde">{{ old('comentario', $invitacion->comentario) }}</textarea>
                    </div>
                    <div class="d-grid d-sm-flex gap-3">
                        @if ($sinCupo)
                            <div class="alert bg-light-warning p-4 mb-0 flex-grow-1 fw-semibold text-gray-800">Ya no hay cupos disponibles para confirmar.</div>
                        @else
                            <button type="submit" name="respuesta" value="confirmada"
                                    class="btn btn-lg btn-success flex-sm-grow-1 {{ $sugerida === 'no' ? 'opacity-75' : '' }}" @if ($sugerida === 'si') autofocus @endif>
                                <i class="ki-outline ki-check fs-2"></i> Sí, asistiré
                            </button>
                        @endif
                        <button type="submit" name="respuesta" value="rechazada"
                                class="btn btn-lg {{ $sugerida === 'no' ? 'btn-danger' : 'btn-light' }} flex-sm-grow-1" @if ($sugerida === 'no') autofocus @endif>
                            <i class="ki-outline ki-cross fs-2"></i> No podré asistir
                        </button>
                    </div>
                </form>

                <div class="separator separator-dashed my-6"></div>
                <a href="{{ route('invitacion.calendario', $invitacion->token) }}" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-calendar-add fs-3"></i> Agregar a mi calendario
                </a>
            @endif
        </div>
    </div>

    <div class="text-center text-muted fs-8">
        ¿No desea recibir más correos de la academia? <a href="{{ route('invitacion.baja', $invitacion->token) }}" class="text-muted text-decoration-underline">Darse de baja</a>
    </div>
@endsection
