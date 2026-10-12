@extends('layouts.app')

@php
    $puedeEditar = auth()->user()->can('contactos.editar') && ! (auth()->user()->sedeRestringida() && $grupo->sede_id === null);
    $segmentos = [\App\Models\Contacto::segmentoDe($grupo->tipo)];
@endphp

@section('title', $grupo->nombre.($grupo->activo ? '' : ' (inactivo)'))
@section('acciones')
    @if ($puedeEditar)
        <a href="{{ route('grupos.edit', $grupo) }}" class="btn btn-sm btn-light">
            <i class="ki-outline ki-pencil fs-2"></i> Editar
        </a>
        <form method="POST" action="{{ route('grupos.estado', $grupo) }}"
              data-confirmar="{{ $grupo->activo ? "¿Desactivar el grupo {$grupo->nombre}? Conserva sus miembros, pero ya no se podrá elegir en nuevas capacitaciones." : "¿Activar el grupo {$grupo->nombre}?" }}">
            @csrf @method('PATCH')
            @if ($grupo->activo)
                <button type="submit" class="btn btn-sm btn-light-warning"><i class="ki-outline ki-lock fs-2"></i> Desactivar</button>
            @else
                <button type="submit" class="btn btn-sm btn-light-success"><i class="ki-outline ki-lock-2 fs-2"></i> Activar</button>
            @endif
        </form>
    @endif
@endsection

@section('content')
    <div class="row g-5 g-xl-8 mb-8">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-gray-500 fw-semibold fs-7 mb-1">Tipo</div>
                <div class="fw-bold fs-5 text-gray-900">{{ \App\Models\Grupo::TIPOS[$grupo->tipo] }}</div>
                <div class="text-gray-500 fw-semibold fs-7 mt-4 mb-1">Sede</div>
                <div class="fw-bold fs-5 text-gray-900">{{ $grupo->sede?->nombre ?? 'Todas las sedes' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body d-flex align-items-center">
                <i class="ki-outline ki-people fs-3x text-primary me-5"></i>
                <div>
                    <div class="fs-2hx fw-bold text-gray-900 lh-1">{{ number_format($miembros->total()) }}</div>
                    <div class="fw-semibold text-gray-600">miembros</div>
                </div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body d-flex align-items-center">
                <i class="ki-outline ki-sms fs-3x text-success me-5"></i>
                <div>
                    <div class="fs-2hx fw-bold text-gray-900 lh-1">{{ number_format($conCorreo) }}</div>
                    <div class="fw-semibold text-gray-600">recibirán invitaciones por correo</div>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex align-items-center position-relative mw-350px w-100">
                <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12" placeholder="Buscar miembro">
            </form>
            @if ($puedeEditar)
                <div class="card-toolbar gap-2">
                    @foreach ($segmentos as $seg)
                        <a href="{{ route('contactos.index', $seg) }}" class="btn btn-sm btn-light-primary">
                            <i class="ki-outline ki-plus fs-3"></i> Agregar {{ mb_strtolower(\App\Models\Contacto::TIPOS[$seg]['plural']) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card-body py-4">
            @if ($puedeEditar && $miembros->total() === 0)
                <div class="text-muted fs-7 mb-4">
                    Para agregar miembros, abra la lista de clientes o clientes, marque a las personas y elija
                    <strong>Agregar a un grupo</strong>. También puede indicar el grupo al cargar un Excel.
                </div>
            @endif
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-200px">Nombre</th>
                        <th>Tipo</th>
                        <th>Correo</th>
                        <th>Sede</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                    @forelse ($miembros as $c)
                        <tr>
                            <td class="text-gray-800 fw-bold">{{ $c->nombre_completo }}</td>
                            <td>{{ $c->oficio ?: '—' }}</td>
                            <td>
                                @if (! $c->correo)
                                    <span class="text-warning">Sin correo</span>
                                @else
                                    {{ $c->correo }}
                                    @unless ($c->acepta_correos)<span class="badge badge-light-danger ms-1">No recibe correos</span>@endunless
                                @endif
                            </td>
                            <td>{{ $c->sede->nombre }}</td>
                            <td class="text-end">
                                @if ($puedeEditar)
                                    <form method="POST" action="{{ route('grupos.quitar', [$grupo, $c]) }}" class="d-inline"
                                          data-confirmar="¿Quitar a {{ $c->nombre_completo }} del grupo?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light btn-active-light-danger">Quitar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-10">Este grupo todavía no tiene miembros.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $miembros->links() }}
        </div>
    </div>
@endsection
