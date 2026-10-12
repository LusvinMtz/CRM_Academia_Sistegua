@extends('layouts.app')

@section('title', 'Grupos')
@section('acciones')
    @can('contactos.editar')
        <a href="{{ route('grupos.create') }}" class="btn btn-sm btn-primary">
            <i class="ki-outline ki-plus fs-2"></i> Nuevo grupo
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3 w-100">
                <div class="d-flex align-items-center position-relative flex-grow-1 mw-350px">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12" placeholder="Buscar grupo">
                </div>
                <select name="tipo" class="form-select form-select-solid w-auto">
                    <option value="">Todos los tipos</option>
                    @foreach (\App\Models\Grupo::TIPOS as $valor => $etq)
                        <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $etq }}</option>
                    @endforeach
                </select>
                @if ($sedes->count() > 1)
                    <select name="sede" class="form-select form-select-solid w-auto">
                        <option value="">Todas las sedes</option>
                        @foreach ($sedes as $s)
                            <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="estado" class="form-select form-select-solid w-auto">
                    <option value="activos">Activos</option>
                    <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
                    <option value="todos" @selected(request('estado') === 'todos')>Activos e inactivos</option>
                </select>
                <button type="submit" class="btn btn-light-primary">Filtrar</button>
                @if (request()->hasAny(['buscar', 'tipo', 'sede', 'estado']))
                    <a href="{{ route('grupos.index') }}" class="btn btn-light">Limpiar</a>
                @endif
            </form>
        </div>
        <div class="card-body py-4">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-200px">Grupo</th>
                        <th>Tipo</th>
                        <th>Sede</th>
                        <th class="text-center">Miembros</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                    @forelse ($grupos as $g)
                        <tr>
                            <td>
                                <a href="{{ route('grupos.show', $g) }}" class="text-gray-800 text-hover-primary fw-bold">{{ $g->nombre }}</a>
                                @unless ($g->activo)<span class="badge badge-light-danger ms-1">Inactivo</span>@endunless
                                @if ($g->descripcion)<span class="d-block fs-7 text-muted">{{ $g->descripcion }}</span>@endif
                            </td>
                            <td>
                                <span class="badge badge-light-{{ 'primary' }} fw-bold">
                                    {{ \App\Models\Grupo::TIPOS[$g->tipo] ?? $g->tipo }}
                                </span>
                            </td>
                            <td>{{ $g->sede?->nombre ?? 'Todas' }}</td>
                            <td class="text-center">{{ number_format($g->contactos_count) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('grupos.show', $g) }}" class="btn btn-sm btn-light btn-active-light-primary">Ver miembros</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-10">
                            No hay grupos. Los grupos sirven para enviar invitaciones a un conjunto de personas, por ejemplo "Instaladores certificados".
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $grupos->links() }}
        </div>
    </div>
@endsection
