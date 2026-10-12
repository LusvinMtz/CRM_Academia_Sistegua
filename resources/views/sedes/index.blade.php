@extends('layouts.app')

@section('title', 'Sedes')
@section('acciones')
    @can('sedes.crear')
        <a href="{{ route('sedes.create') }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> Nueva sede
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        @forelse ($sedes as $sede)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $sede->activa ? '' : 'opacity-75' }}">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title d-flex align-items-center">
                            <div class="symbol symbol-45px me-4">
                                <span class="symbol-label bg-light-primary"><i class="ki-outline ki-bank fs-2x text-primary"></i></span>
                            </div>
                            <div class="d-flex flex-column">
                                <h3 class="fw-bold mb-0">Sede {{ $sede->nombre }}</h3>
                                <span class="text-muted fs-7 fw-semibold">{{ $sede->municipio->nombre }}, {{ $sede->municipio->departamento->nombre }}</span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            @if ($sede->activa)
                                <span class="badge badge-light-success">Activa</span>
                            @else
                                <span class="badge badge-light-danger">Inactiva</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="d-flex flex-wrap gap-3 mb-5">
                            @foreach ([['Clientes', $sede->clientes_count, 'clientes']] as [$etq, $n, $seg])
                                <a href="{{ route('contactos.index', [$seg, 'sede' => $sede->id]) }}"
                                   class="border border-gray-300 border-dashed rounded py-3 px-4 text-hover-primary flex-grow-1">
                                    <div class="fs-2 fw-bold text-gray-900">{{ number_format($n) }}</div>
                                    <div class="fw-semibold text-gray-500 fs-7">{{ $etq }}</div>
                                </a>
                            @endforeach
                            <div class="border border-gray-300 border-dashed rounded py-3 px-4 flex-grow-1">
                                <div class="fs-2 fw-bold text-gray-900">{{ $sede->usuarios_count }}</div>
                                <div class="fw-semibold text-gray-500 fs-7">Usuarios</div>
                            </div>
                        </div>
                        <div class="text-gray-700 fs-7">
                            @if ($sede->direccion)
                                <div class="mb-1"><i class="ki-outline ki-geolocation fs-5 me-1"></i>{{ $sede->direccion }}</div>
                            @else
                                <div class="text-warning fw-semibold mb-1"><i class="ki-outline ki-information-5 fs-5 me-1 text-warning"></i>Falta la dirección: agréguela para que salga en las invitaciones.</div>
                            @endif
                            @if ($sede->telefono)<div class="mb-1"><i class="ki-outline ki-phone fs-5 me-1"></i>{{ $sede->telefono }}</div>@endif
                            @if ($sede->correo)<div><i class="ki-outline ki-sms fs-5 me-1"></i>{{ $sede->correo }}</div>@endif
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2 pt-0 border-0">
                        @can('sedes.editar')
                            <a href="{{ route('sedes.edit', $sede) }}" class="btn btn-sm btn-light btn-active-light-primary">Editar</a>
                        @endcan
                        @can('sedes.desactivar')
                            <form method="POST" action="{{ route('sedes.estado', $sede) }}"
                                  data-confirmar="{{ $sede->activa ? "¿Desactivar la sede {$sede->nombre}? Ya no se podrá elegir al registrar clientes ni capacitaciones; sus datos se conservan." : "¿Activar la sede {$sede->nombre}?" }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-light {{ $sede->activa ? 'btn-active-light-warning' : 'btn-active-light-success' }}">{{ $sede->activa ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-15">No hay sedes registradas.</div></div></div>
        @endforelse
    </div>
@endsection
