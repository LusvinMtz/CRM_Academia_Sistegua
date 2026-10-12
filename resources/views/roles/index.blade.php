@extends('layouts.app')

@section('title', 'Roles y permisos')
@section('acciones')
    @can('roles.crear')
        <a href="{{ route('roles.create') }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> Nuevo rol
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        @foreach ($roles as $rol)
            @php($esAdmin = $rol->name === \App\Models\User::ROL_ADMINISTRADOR)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title flex-column">
                            <h3 class="fw-bold mb-1">{{ $rol->name }}</h3>
                            <span class="text-muted fs-7 fw-semibold">
                                {{ $rol->users_count }} {{ $rol->users_count === 1 ? 'usuario' : 'usuarios' }}
                            </span>
                        </div>
                        @if ($esAdmin)
                            <div class="card-toolbar"><span class="badge badge-light-primary">Acceso total</span></div>
                        @endif
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fw-semibold text-gray-700 me-2">Permisos:</span>
                            <span class="fw-bold text-gray-900">{{ $esAdmin ? $totalPermisos : $rol->permissions_count }} de {{ $totalPermisos }}</span>
                        </div>
                        <div class="h-8px bg-light rounded">
                            <div class="bg-primary rounded h-8px"
                                 style="width: {{ $totalPermisos ? round(($esAdmin ? $totalPermisos : $rol->permissions_count) / $totalPermisos * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2 pt-0 border-0">
                        @can('roles.editar')
                            <a href="{{ route('roles.edit', $rol) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                {{ $esAdmin ? 'Ver permisos' : 'Editar' }}
                            </a>
                        @endcan
                        @if (! $esAdmin)
                            @can('roles.eliminar')
                                <form method="POST" action="{{ route('roles.destroy', $rol) }}"
                                      data-confirmar="¿Eliminar el rol {{ $rol->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light btn-active-light-danger">Eliminar</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
