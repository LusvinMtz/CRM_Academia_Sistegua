@extends('layouts.app')

@section('title', 'Departamentos y municipios')
@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3 w-100">
                <div class="d-flex align-items-center position-relative flex-grow-1 mw-400px">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-solid ps-12"
                           placeholder="Buscar departamento o municipio">
                </div>
                <select name="region" class="form-select form-select-solid w-auto">
                    <option value="">Todas las regiones</option>
                    @foreach ($regiones as $r)
                        <option value="{{ $r }}" @selected(request('region') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-light-primary">Filtrar</button>
                @if (request()->hasAny(['buscar', 'region']))
                    <a href="{{ route('geografia.index') }}" class="btn btn-light">Limpiar</a>
                @endif
            </form>
        </div>
        <div class="card-body py-4">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="w-80px">Código</th>
                        <th>Departamento</th>
                        <th>Región</th>
                        <th class="text-center">Municipios</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                    @forelse ($departamentos as $d)
                        <tr>
                            <td><span class="badge badge-light fw-bold">{{ $d->codigo }}</span></td>
                            <td class="text-gray-800 fw-bold">{{ $d->nombre }}</td>
                            <td>{{ $d->region }}</td>
                            <td class="text-center">{{ $d->municipios_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('geografia.show', $d) }}" class="btn btn-sm btn-light btn-active-light-primary">Ver municipios</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-10">No hay resultados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-muted fs-7 mt-2">Catálogo oficial: 22 departamentos y 340 municipios, con códigos del INE.</div>
        </div>
    </div>
@endsection
