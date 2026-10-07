@extends('layouts.app')

@php
    $esCapacitacion = $evento->tipo === \App\Models\Evento::CAPACITACION;
@endphp

@section('title', 'Asistencia')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('eventos.index', $segmento) }}" class="text-muted text-hover-primary">{{ $config['plural'] }}</a></li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted"><a href="{{ route('eventos.show', [$segmento, $evento]) }}" class="text-muted text-hover-primary">{{ \Illuminate\Support\Str::limit($evento->titulo, 40) }}</a></li>
@endsection

@section('acciones')
    @unless ($evento->cancelado_at)
        <a href="{{ route('asistencia.qr', [$segmento, $evento]) }}" class="btn btn-sm btn-light-success">
            <i class="ki-outline ki-scan-barcode fs-2"></i> QR de asistencia
        </a>
    @endunless
    <a href="{{ route('asistencia.pdf', [$segmento, $evento]) }}" class="btn btn-sm btn-light">
        <i class="ki-outline ki-printer fs-2"></i> Lista para firmas (PDF)
    </a>
    @can('reportes.exportar')
        <a href="{{ route('asistencia.excel', [$segmento, $evento]) }}" class="btn btn-sm btn-light">
            <i class="ki-outline ki-file-down fs-2"></i> Excel
        </a>
    @endcan
@endsection

@section('content')
    <div class="card mb-6">
        <div class="card-body d-flex flex-wrap align-items-center gap-5 py-5">
            <div class="flex-grow-1">
                <div class="fs-4 fw-bold text-gray-900">{{ $evento->titulo }}</div>
                <div class="text-muted fs-7">{{ $evento->horario }} · Sede {{ $evento->sede->nombre }} · {{ $evento->es_virtual ? 'Virtual' : $evento->lugar }}</div>
            </div>
            @foreach ([
                ['En la lista', $resumen['total'], 'gray-800'],
                ['Confirmaron', $resumen['confirmados'], 'primary'],
                ['Presentes', $resumen['presentes'], 'success'],
                ['Ausentes', $resumen['ausentes'], 'danger'],
            ] as [$etq, $n, $color])
                <div class="text-center px-3">
                    <div class="fs-2 fw-bold text-{{ $color }}" @if ($etq === 'Presentes') data-contador-presentes @endif>{{ $n }}</div>
                    <div class="text-muted fs-8 fw-semibold">{{ $etq }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($evento->cancelado_at)
        <div class="alert bg-light-danger border border-danger border-dashed p-5 mb-6 fw-semibold text-gray-800">La actividad está anulada; no se toma asistencia.</div>
    @elseif (! $puedeMarcar)
        <div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-center p-5 mb-6">
            <i class="ki-outline ki-information-5 fs-2x text-primary me-3"></i>
            <span class="fw-semibold text-gray-800">La asistencia se podrá marcar desde el día de la actividad ({{ $evento->inicio->format('d/m/Y') }}). Mientras tanto puede imprimir la lista para firmas.</span>
        </div>
    @endif

    @if ($esCapacitacion && $resumen['presentes'] > 0)
        <div class="card mb-6">
            <div class="card-body d-flex flex-wrap align-items-center gap-4 py-5">
                <i class="ki-outline ki-award fs-2x text-primary"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold text-gray-900">Constancias de participación</div>
                    <div class="text-muted fs-7">{{ $resumen['presentes'] }} {{ $resumen['presentes'] === 1 ? 'cliente asistió' : 'clientes asistieron' }}. Cada constancia lleva un código para verificarla.</div>
                </div>
                <a href="{{ route('constancias.todas', $evento) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-file-down fs-3"></i> Descargar todas (PDF)</a>
                @can('campanias.enviar')
                    <form method="POST" action="{{ route('constancias.enviar', $evento) }}"
                          data-confirmar="¿Enviar por WhatsApp la constancia a los asistentes que tienen teléfono?">
                        @csrf
                        <input type="hidden" name="canal" value="whatsapp">
                        <button type="submit" class="btn btn-sm btn-success"><i class="ki-outline ki-whatsapp fs-3"></i> Enviar por WhatsApp</button>
                    </form>
                    <form method="POST" action="{{ route('constancias.enviar', $evento) }}"
                          data-confirmar="¿Enviar por correo la constancia a los {{ $resumen['presentes'] }} asistentes que tienen correo?">
                        @csrf
                        <input type="hidden" name="canal" value="correo">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ki-outline ki-sms fs-3"></i> Enviar por correo</button>
                    </form>
                @endcan
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header border-0 pt-6 flex-column align-items-stretch gap-4">
            <div class="d-flex flex-wrap flex-stack gap-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ([null => ['Todos', $resumen['total']], 'confirmados' => ['Confirmaron', $resumen['confirmados']], 'presentes' => ['Presentes', $resumen['presentes']], 'ausentes' => ['Ausentes', $resumen['ausentes']], 'sin_marcar' => ['Sin marcar', $resumen['sin_marcar']]] as $clave => [$etq, $n])
                        <a href="{{ request()->fullUrlWithQuery(['filtro' => $clave ?: null]) }}"
                           class="btn btn-sm fw-semibold px-4 {{ (string) $filtro === (string) $clave ? 'btn-primary' : 'btn-light' }}">{{ $etq }} <span class="ms-1 opacity-75">{{ $n }}</span></a>
                    @endforeach
                </div>
                <form method="GET" class="position-relative w-250px">
                    @if ($filtro)<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
                    <i class="ki-outline ki-magnifier fs-4 position-absolute top-50 translate-middle-y ms-3"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-sm form-control-solid ps-10" placeholder="Buscar en la lista">
                </form>
            </div>

            @if ($puedeMarcar)
                <div class="d-flex flex-wrap align-items-center gap-3 bg-light rounded p-4">
                    <span class="fw-semibold text-gray-700 fs-7">¿Llegó alguien que no está en la lista?</span>
                    <div class="position-relative flex-grow-1 mw-400px">
                        <input type="text" class="form-control form-control-sm form-control-solid" placeholder="Buscar por nombre, DPI, empresa u oficio…"
                               data-buscar-asistente="{{ route('asistencia.buscar', [$segmento, $evento]) }}" autocomplete="off">
                        <div class="dropdown-menu w-100 shadow" data-resultados></div>
                    </div>
                    <form method="POST" action="{{ route('asistencia.agregar', [$segmento, $evento]) }}" data-form-agregar class="d-none">
                        @csrf <input type="hidden" name="contacto_id">
                    </form>
                    <a href="{{ route('contactos.create', \App\Models\Contacto::segmentoDe($config['publico'])) }}" target="_blank" class="btn btn-sm btn-link">Registrar persona nueva</a>
                </div>
            @endif
        </div>

        <div class="card-body py-4">
            <form method="POST" action="{{ route('asistencia.guardar', [$segmento, $evento]) }}" id="formAsistencia">
                @csrf
                @if ($puedeMarcar && $lista->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" class="btn btn-sm btn-light-success" data-marcar="confirmados"><i class="ki-outline ki-check fs-4"></i> Presentes todos los que confirmaron</button>
                        <button type="button" class="btn btn-sm btn-light" data-marcar="todos">Marcar todos</button>
                        <button type="button" class="btn btn-sm btn-light" data-marcar="ninguno">Desmarcar todos</button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-3">
                        <thead>
                        <tr class="text-muted fw-bold fs-7 text-uppercase">
                            <th class="w-100px text-center">Presente</th>
                            <th class="min-w-200px">Cliente</th>
                            <th>Empresa y oficio</th>
                            <th>Respuesta</th>
                            <th class="text-end">Registro</th>
                        </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                        @forelse ($lista as $fila)
                            @php($c = $fila['contacto'])
                            @php($inv = $fila['invitacion'])
                            <tr>
                                <td class="text-center">
                                    <input type="hidden" name="contactos[]" value="{{ $c->id }}">
                                    <div class="form-check form-check-custom form-check-solid form-check-success justify-content-center">
                                        <input class="form-check-input h-30px w-30px" type="checkbox" name="presentes[]" value="{{ $c->id }}"
                                               data-confirmado="{{ $inv?->respuesta === 'confirmada' ? 1 : 0 }}"
                                               aria-label="{{ $c->nombre_completo }} presente"
                                               @checked($inv?->asistio) @disabled(! $puedeMarcar)>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-gray-900 fw-bold">{{ $c->nombre_completo }}</div>
                                                                    </td>
                                <td>
                                    {{ $c->empresa ?: '—' }}@if ($c->oficio)<span class="d-block fs-7 text-muted">{{ $c->oficio }}</span>@endif
                                </td>
                                <td>
                                    @if ($inv?->respuesta === 'confirmada')
                                        <span class="badge badge-light-success">Confirmó</span>
                                    @elseif ($inv?->respuesta === 'rechazada')
                                        <span class="badge badge-light-danger">Dijo que no</span>
                                    @elseif (! $inv || $inv->estado_envio === 'no_enviada')
                                        <span class="badge badge-light">{{ $c->correo ? 'Sin invitación' : 'Sin correo' }}</span>
                                    @else
                                        <span class="badge badge-light-warning">Sin respuesta</span>
                                    @endif
                                </td>
                                <td class="text-end text-muted fs-7">
                                    @if ($inv?->asistio !== null)
                                        {{ $inv->asistio ? 'Presente' : 'Ausente' }} · {{ $inv->asistencia_at?->format('d/m H:i') }}
                                        @if ($inv->asistio && $inv->asistencia_metodo && $inv->asistencia_metodo !== 'manual')
                                            <span class="d-block fs-8 text-primary">{{ \App\Models\Invitacion::METODOS[$inv->asistencia_metodo] ?? '' }}</span>
                                        @endif
                                    @else
                                        Sin marcar
                                    @endif
                                    @if ($esCapacitacion && $inv?->asistio)
                                        <a href="{{ route('constancias.descargar', [$evento, $inv]) }}" class="ms-2" data-bs-toggle="tooltip" title="Descargar constancia">
                                            <i class="ki-outline ki-award fs-3 text-primary"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-10">No hay personas en esta lista.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($puedeMarcar && $lista->isNotEmpty())
                    <div class="d-flex flex-stack flex-wrap gap-3 pt-4 border-top border-dashed">
                        <span class="text-muted fs-7">Los cambios se guardan al pulsar el botón. Si filtró la lista, solo se guardan las personas visibles.</span>
                        <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check-square fs-2"></i> Guardar asistencia</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('formAsistencia');
            var casillas = form.querySelectorAll('input[name="presentes[]"]');
            var contador = document.querySelector('[data-contador-presentes]');
            var cambios = false;
            function contar() {
                if (contador) contador.textContent = Array.prototype.filter.call(casillas, function (c) { return c.checked; }).length;
            }
            casillas.forEach(function (c) { c.addEventListener('change', function () { cambios = true; contar(); }); });
            form.querySelectorAll('[data-marcar]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    casillas.forEach(function (c) {
                        if (btn.dataset.marcar === 'todos') c.checked = true;
                        else if (btn.dataset.marcar === 'ninguno') c.checked = false;
                        else if (c.dataset.confirmado === '1') c.checked = true;
                    });
                    cambios = true; contar();
                });
            });
            form.addEventListener('submit', function () { cambios = false; });
            window.addEventListener('beforeunload', function (e) { if (cambios) { e.preventDefault(); e.returnValue = ''; } });

            // Buscar y agregar asistentes que no estaban en la lista
            var input = document.querySelector('[data-buscar-asistente]');
            if (!input) return;
            var menu = document.querySelector('[data-resultados]');
            var formAgregar = document.querySelector('[data-form-agregar]');
            var espera;
            input.addEventListener('input', function () {
                clearTimeout(espera);
                var q = input.value.trim();
                if (q.length < 2) { menu.classList.remove('show'); return; }
                espera = setTimeout(function () {
                    fetch(input.dataset.buscarAsistente + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (lista) {
                            menu.innerHTML = '';
                            if (!lista.length) {
                                menu.innerHTML = '<span class="dropdown-item-text text-muted fs-7">No se encontró a nadie de esta sede fuera de la lista.</span>';
                            }
                            lista.forEach(function (p) {
                                var b = document.createElement('button');
                                b.type = 'button';
                                b.className = 'dropdown-item';
                                b.innerHTML = '<span class="fw-bold"></span><span class="d-block text-muted fs-8"></span>';
                                b.children[0].textContent = p.nombre;
                                b.children[1].textContent = p.detalle;
                                b.addEventListener('click', function () {
                                    formAgregar.querySelector('[name="contacto_id"]').value = p.id;
                                    formAgregar.dataset.confirmar = '¿Registrar la asistencia de ' + p.nombre + '?' + (cambios ? ' Los cambios sin guardar de la lista se perderán.' : '');
                                    cambios = false;
                                    menu.classList.remove('show');
                                    formAgregar.requestSubmit();
                                });
                                menu.appendChild(b);
                            });
                            menu.classList.add('show');
                        });
                }, 250);
            });
            document.addEventListener('click', function (e) { if (!menu.contains(e.target) && e.target !== input) menu.classList.remove('show'); });
        })();
    </script>
@endpush
