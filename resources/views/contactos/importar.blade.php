@extends('layouts.app')

@section('title', 'Cargar '.mb_strtolower($config['plural']).' desde Excel')
@section('content')
    @if ($resultado)
        @php($r = $resultado)
        <div class="card mb-8">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title fw-bold">Resultado de la carga</h3>
                <div class="card-toolbar">
                    <a href="{{ route('contactos.index', $segmento) }}" class="btn btn-sm btn-primary">Ver {{ mb_strtolower($config['plural']) }}</a>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="row g-4 mb-6">
                    @foreach ([
                        ['Filas leídas', $r['total'], 'gray-700', 'ki-document'],
                        ['Nuevos', $r['creados'], 'success', 'ki-plus-circle'],
                        ['Actualizados', $r['actualizados'], 'primary', 'ki-arrows-circle'],
                        ['Sin cambios (ya existían)', $r['omitidos'], 'warning', 'ki-minus-circle'],
                        ['Con errores', count($r['errores']), 'danger', 'ki-cross-circle'],
                    ] as [$etq, $n, $color, $icono])
                        <div class="col-6 col-md">
                            <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                                <i class="ki-outline {{ $icono }} fs-2 text-{{ $color }}"></i>
                                <div class="fs-2 fw-bold text-gray-900 mt-2">{{ $n }}</div>
                                <div class="text-gray-600 fs-7 fw-semibold">{{ $etq }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($r['grupos_creados'])
                    <div class="alert bg-light-primary border border-primary border-dashed p-4 mb-6">
                        <span class="fw-semibold text-gray-800">Se crearon estos grupos nuevos: {{ implode(', ', $r['grupos_creados']) }}.</span>
                    </div>
                @endif

                @if ($r['errores'])
                    <h4 class="fw-bold text-danger mb-3">Filas que no se cargaron</h4>
                    <p class="text-gray-600 fs-7">Corrija estas filas en su archivo y vuelva a cargarlo. Las filas correctas ya se guardaron; si las vuelve a subir, solo se actualizan.</p>
                    <div class="table-responsive mh-400px overflow-auto">
                        <table class="table table-row-dashed table-sm align-middle fs-7 gy-2">
                            <thead><tr class="text-muted fw-bold text-uppercase"><th class="w-80px">Fila</th><th>Problema</th></tr></thead>
                            <tbody>
                            @foreach ($r['errores'] as $e)
                                <tr><td class="fw-bold">{{ $e['fila'] }}</td><td class="text-gray-800">{{ $e['mensaje'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($r['total'] > 0)
                    <div class="alert bg-light-success border border-success border-dashed p-4 mb-0">
                        <span class="fw-semibold text-gray-800">Todas las filas se cargaron correctamente.</span>
                    </div>
                @else
                    <div class="alert bg-light-warning border border-warning border-dashed p-4 mb-0">
                        <span class="fw-semibold text-gray-800">El archivo no tenía filas con datos.</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-5 g-xl-8">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('contactos.importar.store', $segmento) }}" enctype="multipart/form-data" class="card h-100" novalidate>
                @csrf
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Subir archivo</h3>
                </div>
                <div class="card-body pt-2">
                    <div class="mb-8">
                        <label for="archivo" class="required form-label fw-semibold">Archivo de Excel (.xlsx) o CSV</label>
                        <input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                               class="form-control form-control-solid @error('archivo') is-invalid @enderror">
                        @error('archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Máximo 10 MB y {{ number_format(\App\Services\ImportadorContactos::MAX_FILAS) }} filas.</div>
                    </div>

                    <div class="mb-8">
                        <label class="form-label fw-semibold">Si la persona ya está registrada (mismo correo o DPI)</label>
                        <label class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="radio" name="existentes" value="actualizar" @checked(old('existentes', 'actualizar') === 'actualizar')>
                            <span class="form-check-label text-gray-700">Actualizar sus datos con los del archivo</span>
                        </label>
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="radio" name="existentes" value="omitir" @checked(old('existentes') === 'omitir')>
                            <span class="form-check-label text-gray-700">Dejarla como está</span>
                        </label>
                    </div>

                    <div>
                        <label for="grupo_id" class="form-label fw-semibold">Agregar a todos a un grupo (opcional)</label>
                        <select id="grupo_id" name="grupo_id" class="form-select form-select-solid">
                            <option value="">Ninguno</option>
                            @foreach ($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->nombre }}{{ $g->sede ? ' ('.$g->sede->nombre.')' : '' }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">También puede indicar grupos por persona en la columna "Grupos" del archivo.</div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-3 py-6">
                    <a href="{{ route('contactos.index', $segmento) }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary" data-cargando>
                        <i class="ki-outline ki-file-up fs-2"></i> Cargar archivo
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Formato del archivo</h3>
                </div>
                <div class="card-body pt-2">
                    <a href="{{ route('contactos.plantilla', $segmento) }}" class="btn btn-light-success w-100 mb-6">
                        <i class="ki-outline ki-file-down fs-2"></i> Descargar plantilla de ejemplo
                    </a>
                    <div class="text-gray-700 fs-7 mb-3">La primera fila debe tener estos títulos:</div>
                    <div class="d-flex flex-wrap gap-2 mb-6">
                        @foreach ($columnas as $campo => $titulo)
                            <span class="badge {{ in_array($campo, ['nombres', 'apellidos', 'sede']) ? 'badge-primary' : 'badge-light' }} fw-semibold fs-7">{{ $titulo }}</span>
                        @endforeach
                    </div>
                    <ul class="text-gray-700 fs-7 ps-4 mb-0">
                        <li class="mb-2">Las columnas en azul son obligatorias.</li>
                        <li class="mb-2">Sede: {{ $sedes->pluck('nombre')->join(', ') }} (sin importar tildes o mayúsculas).</li>
                        <li class="mb-2">DPI de 13 dígitos y teléfono de 8 dígitos; se aceptan espacios y guiones.</li>
                        <li class="mb-2">Grupos separados por coma. Si un grupo no existe, se crea en la sede de la persona.</li>
                        <li>Las filas con errores se informan y no detienen la carga del resto.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-cargando]').forEach(function (btn) {
            btn.form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando…';
            });
        });
    </script>
@endpush
