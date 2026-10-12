@extends('layouts.app')

@section('title', $config['plural'])
@section('acciones')
    @can('eventos.crear')
        <a href="{{ route('eventos.create', $segmento) }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> {{ $config['nuevo'] }}
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6 flex-column align-items-stretch gap-4">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold">
                @foreach (['proximos' => 'Próximos', 'realizados' => 'Realizados', 'cancelados' => 'Anulados', 'todos' => 'Todos'] as $clave => $etq)
                    <li class="nav-item">
                        <a class="nav-link text-active-primary {{ $estado === $clave ? 'active' : '' }}"
                           href="{{ route('eventos.index', [$segmento, 'estado' => $clave] + request()->only(['buscar', 'sede', 'modalidad'])) }}">{{ $etq }}</a>
                    </li>
                @endforeach
            </ul>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
                <input type="hidden" name="estado" value="{{ $estado }}">
                <div class="d-flex align-items-center position-relative flex-grow-1 mw-350px">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12"
                           placeholder="{{ $config['tipo'] === 'capacitacion' ? 'Título o facilitador' : 'Título' }}">
                </div>
                @if ($sedes->count() > 1)
                    <select name="sede" class="form-select form-select-solid w-auto">
                        <option value="">Todas las sedes</option>
                        @foreach ($sedes as $s)
                            <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="modalidad" class="form-select form-select-solid w-auto">
                    <option value="">Presencial y virtual</option>
                    <option value="presencial" @selected(request('modalidad') === 'presencial')>Presencial</option>
                    <option value="virtual" @selected(request('modalidad') === 'virtual')>Virtual</option>
                </select>
                <button type="submit" class="btn btn-light-primary">Filtrar</button>
                @if (request()->hasAny(['buscar', 'sede', 'modalidad']))
                    <a href="{{ route('eventos.index', [$segmento, 'estado' => $estado]) }}" class="btn btn-light">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="card-body py-4">
            @forelse ($eventos as $e)
                @php([$estadoTxt, $estadoColor] = $e->estado_etiqueta)
                <a href="{{ route('eventos.show', [$segmento, $e]) }}"
                   class="d-flex flex-wrap flex-md-nowrap align-items-center gap-5 border border-dashed border-gray-300 rounded p-5 mb-4 text-hover-primary evento-fila {{ $e->cancelado_at ? 'opacity-75' : '' }}">
                    <div class="evento-fecha text-center rounded bg-light-{{ $e->cancelado_at ? 'danger' : 'primary' }} px-4 py-3 flex-shrink-0">
                        <div class="fs-8 fw-bold text-uppercase text-{{ $e->cancelado_at ? 'danger' : 'primary' }}">{{ $e->inicio->translatedFormat('M') }}</div>
                        <div class="fs-2hx fw-bolder text-gray-900 lh-1">{{ $e->inicio->format('d') }}</div>
                        <div class="fs-8 fw-semibold text-gray-600">{{ $e->inicio->translatedFormat('D') }}</div>
                    </div>
                    <div class="flex-grow-1 min-w-200px">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fs-5 fw-bold text-gray-900 {{ $e->cancelado_at ? 'text-decoration-line-through' : '' }}">{{ $e->titulo }}</span>
                            <span class="badge badge-light-{{ $estadoColor }}">{{ $estadoTxt }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-4 text-gray-600 fs-7 fw-semibold">
                            <span><i class="ki-outline ki-time fs-5 me-1"></i>{{ $e->inicio->format('H:i') }} – {{ $e->fin->format('H:i') }}</span>
                            <span><i class="ki-outline ki-bank fs-5 me-1"></i>Sede {{ $e->sede->nombre }}</span>
                            @if ($e->es_virtual)
                                <span><i class="ki-outline ki-screen fs-5 me-1"></i>Virtual · {{ $e->plataforma }}</span>
                            @else
                                <span class="text-truncate mw-300px"><i class="ki-outline ki-geolocation fs-5 me-1"></i>{{ $e->lugar }}</span>
                            @endif
                            @if ($e->facilitador)
                                <span><i class="ki-outline ki-teacher fs-5 me-1"></i>{{ $e->facilitador }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-md-end text-gray-600 fs-7 fw-semibold flex-shrink-0">
                        @if ($e->para_todos)
                            Todos los {{ $config['publico_texto'] }}
                        @else
                            {{ $e->grupos->pluck('nombre')->take(2)->join(', ') }}{{ $e->grupos->count() > 2 ? ' y '.($e->grupos->count() - 2).' más' : '' }}
                        @endif
                    </div>
                </a>
            @empty
                <div class="text-center py-15">
                    <i class="ki-outline {{ $config['icono'] }} fs-5x text-gray-300 mb-4"></i>
                    <div class="text-muted mb-5">
                        @if ($estado === 'proximos')
                            No hay {{ mb_strtolower($config['plural']) }} programadas.
                        @else
                            No hay {{ mb_strtolower($config['plural']) }} en esta lista.
                        @endif
                    </div>
                    @can('eventos.crear')
                        <a href="{{ route('eventos.create', $segmento) }}" class="btn btn-sm btn-primary">{{ $config['nuevo'] }}</a>
                    @endcan
                </div>
            @endforelse
            {{ $eventos->links() }}
        </div>
    </div>
@endsection
