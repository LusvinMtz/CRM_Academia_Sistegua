@extends('layouts.app')

@section('title', 'Usuarios')
@section('acciones')
    @can('usuarios.crear')
        <a href="{{ route('usuarios.create') }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> Nuevo usuario
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3 w-100">
                <div class="d-flex align-items-center position-relative flex-grow-1 mw-400px">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12"
                           placeholder="Buscar por nombre, correo o DPI">
                </div>
                <select name="rol" class="form-select form-select-solid w-auto">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r }}" @selected(request('rol') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                <select name="estado" class="form-select form-select-solid w-auto">
                    <option value="">Todos los estados</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                </select>
                <button type="submit" class="btn btn-light-primary">Filtrar</button>
                @if (request()->hasAny(['buscar', 'rol', 'estado']))
                    <a href="{{ route('usuarios.index') }}" class="btn btn-light">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="card-body py-4">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-200px">Usuario</th>
                        <th>Rol</th>
                        <th>Sede</th>
                        <th>Teléfono</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                    @forelse ($usuarios as $u)
                        <tr>
                            <td class="d-flex align-items-center">
                                <div class="symbol symbol-circle symbol-45px me-4">
                                    <span class="symbol-label bg-light-primary text-primary fw-bold">{{ $u->iniciales }}</span>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="text-gray-800 fw-bold mb-1">{{ $u->nombre_completo }}</span>
                                    <span class="fs-7">{{ $u->email }}</span>
                                </div>
                            </td>
                            <td><span class="badge badge-light-primary fw-bold">{{ $u->getRoleNames()->first() ?? 'Sin rol' }}</span></td>
                            <td>{{ $u->sede?->nombre ?? 'Todas' }}</td>
                            <td>{{ $u->telefono ?: '—' }}</td>
                            <td>{{ $u->ultimo_acceso_at?->diffForHumans() ?? 'Nunca' }}</td>
                            <td>
                                @if ($u->activo)
                                    <span class="badge badge-light-success fw-bold">Activo</span>
                                @else
                                    <span class="badge badge-light-danger fw-bold">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('usuarios.desactivar')
                                    <form method="POST" action="{{ route('usuarios.estado', $u) }}" class="d-inline"
                                          data-confirmar="{{ $u->activo ? "¿Desactivar a {$u->nombre_completo}? Ya no podrá ingresar." : "¿Activar a {$u->nombre_completo}?" }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-icon btn-bg-light btn-active-color-warning btn-sm me-1"
                                                data-bs-toggle="tooltip" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                            <i class="ki-outline {{ $u->activo ? 'ki-lock' : 'ki-lock-2' }} fs-3"></i>
                                        </button>
                                    </form>
                                @endcan
                                @can('usuarios.editar')
                                    <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm"
                                       data-bs-toggle="tooltip" title="Editar">
                                        <i class="ki-outline ki-pencil fs-3"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-10">No se encontraron usuarios.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $usuarios->links() }}
        </div>
    </div>
@endsection
