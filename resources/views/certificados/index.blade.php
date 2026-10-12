@extends('layouts.app')

@section('title', 'Diseños de certificado')
@section('acciones')
    @can('certificados.crear')
        <form method="POST" action="{{ route('certificados.create') }}">
            @csrf
            <button class="btn btn-primary btn-sm">
                <i class="ki-outline ki-plus fs-3"></i> Nuevo diseño
            </button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <p class="text-gray-600 mb-7">
                Cada capacitación entrega su constancia con el diseño predeterminado, salvo que se le
                asigne uno propio desde la ficha de la capacitación.
            </p>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-muted fw-bold fs-7 text-uppercase">
                        <th class="min-w-250px">Diseño</th>
                        <th>Orientación</th>
                        <th>Elementos</th>
                        <th>Capacitaciones que lo usan</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                    @forelse ($plantillas as $p)
                        <tr>
                            <td>
                                <span class="text-gray-900 fw-bold">{{ $p->nombre }}</span>
                                @if ($p->predeterminada)
                                    <span class="badge badge-light-success ms-2">Predeterminado</span>
                                @endif
                            </td>
                            <td>{{ $p->orientacion === 'vertical' ? 'Vertical' : 'Horizontal' }}</td>
                            <td>{{ count($p->elementos) }}</td>
                            <td>{{ $p->eventos_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('certificados.vista-previa', $p) }}" target="_blank"
                                   class="btn btn-sm btn-light-primary" title="Ver un PDF de ejemplo">Vista previa</a>
                                @can('certificados.editar')
                                    <a href="{{ route('certificados.edit', $p) }}" class="btn btn-sm btn-light">Editar</a>
                                    @unless ($p->predeterminada)
                                        <form method="POST" action="{{ route('certificados.predeterminada', $p) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light">Usar por defecto</button>
                                        </form>
                                    @endunless
                                @endcan
                                @can('certificados.crear')
                                    <form method="POST" action="{{ route('certificados.duplicar', $p) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-light">Duplicar</button>
                                    </form>
                                @endcan
                                @can('certificados.eliminar')
                                    <form method="POST" action="{{ route('certificados.destroy', $p) }}" class="d-inline"
                                          data-confirmar="¿Eliminar el diseño &quot;{{ $p->nombre }}&quot;?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light-danger">Eliminar</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-10">
                                Todavía no hay diseños de certificado.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
