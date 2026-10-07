@php
    $puedeEnviar = auth()->user()->can('campanias.enviar') && $evento->acepta_respuestas;
    $plantillaInicial = $plantillas->first();
    $pestanas = [
        null => ['Todas', $resumen['invitados']],
        'confirmadas' => ['Confirmaron', $resumen['confirmadas']],
        'rechazadas' => ['No asistirán', $resumen['rechazadas']],
        'sin_respuesta' => ['Sin respuesta', $resumen['sin_respuesta']],
        'fallidas' => ['Con error', $resumen['fallidas']],
    ];
@endphp
<div class="card mb-5 mb-xl-8" id="invitaciones">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold text-gray-900">Invitaciones y respuestas</span>
            <span class="text-muted mt-1 fw-semibold fs-7">Control desde el envío hasta la confirmación</span>
        </h3>
        @if ($puedeEnviar)
            <div class="card-toolbar gap-2">
                @if ($porRecordar + $confirmadosPorRecordar + $porRecordarWhatsapp + $confirmadosPorRecordarWhatsapp > 0)
                    <button type="button" class="btn btn-sm btn-light-warning" data-bs-toggle="modal" data-bs-target="#modalRecordar">
                        <i class="ki-outline ki-notification-on fs-3"></i> Enviar recordatorio
                    </button>
                @endif
                @if ($porInvitar + $porInvitarWhatsapp > 0 || $resumen['invitados'] === 0)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalInvitar" @disabled($porInvitar + $porInvitarWhatsapp === 0)>
                        <i class="ki-outline ki-send fs-3"></i>
                        {{ $resumen['invitados'] ? 'Invitar a nuevos' : 'Enviar invitaciones' }}
                    </button>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body pt-2">
        @if ($modoPrueba)
            <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-center p-4 mb-6">
                <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
                <span class="fw-semibold text-gray-800 fs-7">
                    <strong>Modo de prueba:</strong> los correos no se envían de verdad; se guardan en <code>storage/logs/correos.log</code>.
                    Puede ver cómo quedan con la vista previa y probar las respuestas con el enlace de cada invitado.
                </span>
            </div>
        @endif
        @if ($enviosDetenidos > 0)
            <div class="alert bg-light-danger border border-danger border-dashed d-flex align-items-center p-4 mb-6">
                <i class="ki-outline ki-time fs-2x text-danger me-3"></i>
                <span class="fw-semibold text-gray-800 fs-7">
                    Hay {{ $enviosDetenidos }} {{ $enviosDetenidos === 1 ? 'envío atrasado' : 'envíos atrasados' }}. El sistema los reanuda solo
                    (al abrir esta página se vuelven a poner en marcha). Si el aviso sigue después de unos minutos, revise <code>storage/logs/laravel.log</code>.
                </span>
            </div>
        @endif
        @if ($modoPruebaWhatsapp && $puedeEnviar)
            <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-center p-4 mb-6">
                <i class="ki-outline ki-whatsapp fs-2x text-warning me-3"></i>
                <span class="fw-semibold text-gray-800 fs-7">
                    <strong>WhatsApp en modo de prueba:</strong> los mensajes no se envían de verdad; se guardan en <code>storage/logs/whatsapp.log</code>.
                </span>
            </div>
        @endif

        @if (! $evento->acepta_respuestas && $resumen['invitados'] === 0)
            <div class="text-muted">
                {{ $evento->cancelado_at ? 'La actividad está anulada.' : 'La actividad ya se realizó.' }} No se enviaron invitaciones.
            </div>
        @elseif ($resumen['invitados'] === 0)
            <div class="text-center py-8">
                <i class="ki-outline ki-sms fs-5x text-gray-300 mb-4"></i>
                <div class="text-gray-700 fw-semibold mb-1">Todavía no se han enviado invitaciones.</div>
                <div class="text-muted fs-7">
                    {{ $porInvitar }} {{ $porInvitar === 1 ? 'persona tiene' : 'personas tienen' }} correo y
                    {{ $porInvitarWhatsapp }} {{ $porInvitarWhatsapp === 1 ? 'tiene' : 'tienen' }} teléfono para WhatsApp.
                </div>
            </div>
        @else
            {{-- Resumen --}}
            <div class="row g-3 mb-6">
                @foreach ([
                    ['Invitados', $resumen['invitados'], 'gray-800'],
                    ['Vieron la invitación', $resumen['vistas'], 'info'],
                    ['Confirmaron', $resumen['confirmadas'], 'success'],
                    ['No asistirán', $resumen['rechazadas'], 'danger'],
                    ['Sin respuesta', $resumen['sin_respuesta'], 'warning'],
                ] as [$etq, $n, $color])
                    <div class="col-6 col-md">
                        <div class="border border-dashed border-gray-300 rounded p-3 h-100">
                            <div class="fs-2 fw-bold text-{{ $color }}">{{ number_format($n) }}</div>
                            <div class="text-gray-600 fs-8 fw-semibold">{{ $etq }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($resumen['invitados'] > 0)
                @php($pct = round($resumen['confirmadas'] / $resumen['invitados'] * 100))
                <div class="d-flex align-items-center gap-3 mb-6">
                    <div class="h-8px bg-light rounded flex-grow-1 overflow-hidden d-flex">
                        <div class="bg-success h-8px" style="width: {{ $pct }}%"></div>
                        <div class="bg-danger h-8px" style="width: {{ round($resumen['rechazadas'] / $resumen['invitados'] * 100) }}%"></div>
                    </div>
                    <span class="fw-bold text-gray-700 fs-7">{{ $pct }}% confirmó</span>
                    @if ($evento->cupo)
                        <span class="badge badge-light{{ $evento->cupo_lleno ? '-danger' : '' }} fs-8">{{ $resumen['confirmadas'] }}/{{ $evento->cupo }} cupos</span>
                    @endif
                </div>
            @endif

            {{-- Pestañas y búsqueda --}}
            <div class="d-flex flex-wrap flex-stack gap-3 mb-4">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($pestanas as $clave => [$etq, $n])
                        <a href="{{ request()->fullUrlWithQuery(['situacion' => $clave ?: null, 'pagina' => null]) }}#invitaciones"
                           class="btn btn-sm fw-semibold px-4 {{ (string) $situacion === (string) $clave ? 'btn-primary' : 'btn-light' }}">
                            {{ $etq }} <span class="ms-1 opacity-75">{{ $n }}</span>
                        </a>
                    @endforeach
                </div>
                <form method="GET" action="{{ url()->current() }}#invitaciones" class="position-relative w-250px">
                    @if ($situacion)<input type="hidden" name="situacion" value="{{ $situacion }}">@endif
                    <i class="ki-outline ki-magnifier fs-4 position-absolute top-50 translate-middle-y ms-3"></i>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-sm form-control-solid ps-10" placeholder="Buscar invitado">
                </form>
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-3">
                    <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-8">
                        <th class="min-w-200px">Invitado</th>
                        <th>Situación</th>
                        <th>Respondió</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                    @forelse ($invitaciones as $inv)
                        @php([$sitTxt, $sitColor, $sitIcono] = $inv->situacion)
                        <tr>
                            <td>
                                <div class="text-gray-900 fw-bold">{{ $inv->contacto->nombre_completo }}</div>
                                <div class="text-muted">
                                    {{ $inv->correo ?? $inv->contacto->correo }}@if (($inv->correo ?? $inv->contacto->correo) && $inv->contacto->telefono) · @endif{{ $inv->contacto->telefono }}@if ($inv->contacto->empresa) · {{ $inv->contacto->empresa }}@endif
                                </div>
                                <div class="d-flex gap-1 mt-1">
                                    @foreach (\App\Models\Invitacion::CANALES as $canal => $etqCanal)
                                        @if ($estadoCanal = $inv->{$canal.'_estado'})
                                            <span class="badge badge-light-{{ ['enviada' => 'success', 'pendiente' => 'warning', 'fallida' => 'danger'][$estadoCanal] ?? 'secondary' }} fs-9"
                                                  title="{{ $etqCanal }}: {{ $estadoCanal }}">
                                                <i class="ki-outline {{ $canal === 'whatsapp' ? 'ki-whatsapp' : 'ki-sms' }} fs-8 me-1"></i>{{ $etqCanal }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-light-{{ $sitColor }}"><i class="ki-outline {{ $sitIcono }} fs-7 me-1 text-{{ $sitColor }}"></i>{{ $sitTxt }}</span>
                                @if ($inv->error)<div class="text-danger fs-8 mt-1 text-truncate mw-250px" title="{{ $inv->error }}">{{ $inv->error }}</div>@endif
                                @if ($inv->comentario)<div class="text-gray-600 fs-8 mt-1 fst-italic">"{{ $inv->comentario }}"</div>@endif
                            </td>
                            <td class="text-nowrap">
                                @if ($inv->respondida_at)
                                    {{ $inv->respondida_at->format('d/m H:i') }}
                                    <div class="text-muted fs-8">{{ $inv->respuesta_por === 'personal' ? 'Registrado por la academia' : 'Por correo' }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('campanias.enviar')
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="btn btn-sm btn-light btn-active-light-primary" data-bs-toggle="dropdown" aria-expanded="false">
                                            Acciones <i class="ki-outline ki-down fs-6 ms-1"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end py-2">
                                            @foreach ([['confirmada', 'Marcar que asistirá', 'ki-check-circle'], ['rechazada', 'Marcar que no asistirá', 'ki-cross-circle'], ['', 'Borrar respuesta', 'ki-arrows-circle']] as [$valor, $etq, $icono])
                                                @if ($inv->respuesta !== ($valor ?: null))
                                                    <form method="POST" action="{{ route('invitaciones.responder', [$segmento, $evento, $inv]) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="respuesta" value="{{ $valor }}">
                                                        <button type="submit" class="dropdown-item fs-7"><i class="ki-outline {{ $icono }} fs-5 me-2"></i>{{ $etq }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                            @if ($evento->acepta_respuestas)
                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="{{ route('invitaciones.reenviar', [$segmento, $evento, $inv]) }}">
                                                    @csrf
                                                    @if ($inv->contacto->correo)
                                                        <button type="submit" name="canal" value="correo" class="dropdown-item fs-7">
                                                            <i class="ki-outline ki-sms fs-5 me-2"></i>{{ $inv->correo_estado ? 'Reenviar' : 'Enviar' }} por correo
                                                        </button>
                                                    @endif
                                                    @if ($inv->contacto->telefono)
                                                        <button type="submit" name="canal" value="whatsapp" class="dropdown-item fs-7">
                                                            <i class="ki-outline ki-whatsapp fs-5 me-2"></i>{{ $inv->whatsapp_estado ? 'Reenviar' : 'Enviar' }} por WhatsApp
                                                        </button>
                                                    @endif
                                                </form>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            <a href="{{ $inv->url() }}" target="_blank" rel="noopener" class="dropdown-item fs-7">
                                                <i class="ki-outline ki-exit-right-corner fs-5 me-2"></i>Abrir página del invitado
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-8">No hay invitados en esta lista.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $invitaciones->fragment('invitaciones')->links() }}
        @endif

        @if ($resumen['invitados'] > 0)
            <div class="d-flex align-items-center gap-3 bg-light rounded px-4 py-3 mt-6 fs-7">
                <i class="ki-outline ki-notification-on fs-3 text-primary"></i>
                <span class="text-gray-700"><strong class="text-gray-800">Recordatorio:</strong> {{ $evento->estado_recordatorio }}</span>
            </div>
        @endif

        @if ($evento->envios->isNotEmpty())
            <div class="separator separator-dashed my-6"></div>
            <h5 class="fw-bold text-gray-800 mb-3">Historial de envíos</h5>
            @foreach ($evento->envios as $envio)
                <div class="d-flex align-items-center gap-3 mb-2 fs-7">
                    <span class="badge badge-light">{{ \App\Models\Envio::MOTIVOS[$envio->motivo] ?? $envio->motivo }}</span>
                    @if ($envio->canal === 'whatsapp')
                        <span class="text-gray-700"><i class="ki-outline ki-whatsapp fs-6 text-success"></i> {{ $envio->total }} {{ $envio->total === 1 ? 'mensaje' : 'mensajes' }} de WhatsApp</span>
                    @else
                        <span class="text-gray-700"><i class="ki-outline ki-sms fs-6"></i> {{ $envio->total }} {{ $envio->total === 1 ? 'correo' : 'correos' }}</span>
                    @endif
                    <span class="text-muted">{{ $envio->created_at->format('d/m/Y H:i') }}{{ $envio->usuario ? ' · '.$envio->usuario->nombre_completo : '' }}</span>
                </div>
            @endforeach
        @endif
    </div>
</div>

@if ($puedeEnviar)
    {{-- Enviar invitaciones --}}
    <div class="modal fade" id="modalInvitar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="{{ route('invitaciones.enviar', [$segmento, $evento]) }}" class="modal-content" data-form-correo>
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title fs-4">Enviar invitaciones</h3>
                    <button type="button" class="btn btn-icon btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"><i class="ki-outline ki-cross fs-1"></i></button>
                </div>
                <div class="modal-body">
                    @if ($plantillas->count() > 1)
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Plantilla</label>
                            <select class="form-select form-select-solid" data-selector-plantilla>
                                @foreach ($plantillas as $p)
                                    <option data-asunto="{{ $p->asunto }}" data-mensaje="{{ $p->mensaje }}">{{ $p->nombre }}{{ $p->predeterminada ? ' (predeterminada)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @include('eventos._campos_correo', [
                        'asunto' => $plantillaInicial?->asunto ?? 'Invitación: {titulo}',
                        'mensaje' => $plantillaInicial?->mensaje ?? '',
                    ])
                    <div class="text-muted fs-7 mt-4">
                        Cada canal se envía solo a quienes todavía no recibieron la invitación por ese medio; si después agrega personas al grupo,
                        podrá invitarlas sin repetir el mensaje a los demás. Por WhatsApp no se usa el asunto: se envía el mensaje con la fecha,
                        el lugar y el enlace para confirmar.
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-light me-auto" data-vista-previa="{{ route('invitaciones.vista-previa', [$segmento, $evento]) }}" data-motivo="invitacion">
                        <i class="ki-outline ki-eye fs-3"></i> Vista previa
                    </a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="canal" value="whatsapp" class="btn btn-success" data-cargando @disabled($porInvitarWhatsapp === 0)>
                        <i class="ki-outline ki-whatsapp fs-3"></i> Por WhatsApp ({{ $porInvitarWhatsapp }})
                    </button>
                    <button type="submit" name="canal" value="correo" class="btn btn-primary" data-cargando @disabled($porInvitar === 0)>
                        <i class="ki-outline ki-sms fs-3"></i> Por correo ({{ $porInvitar }})
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recordatorio --}}
    <div class="modal fade" id="modalRecordar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="{{ route('invitaciones.recordar', [$segmento, $evento]) }}" class="modal-content" data-form-correo>
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title fs-4">Enviar recordatorio</h3>
                    <button type="button" class="btn btn-icon btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"><i class="ki-outline ki-cross fs-1"></i></button>
                </div>
                <div class="modal-body">
                    @if ($evento->recordatorio_enviado_at)
                        <div class="alert bg-light-warning border border-warning border-dashed p-4 mb-5 fs-7 fw-semibold text-gray-800">
                            Ya se envió un recordatorio el {{ $evento->recordatorio_enviado_at->translatedFormat('j \d\e F \a \l\a\s H:i') }}. Puede enviarlo otra vez si lo necesita.
                        </div>
                    @else
                        <div class="text-muted fs-7 mb-5">Se recomienda enviarlo el día anterior a la actividad. No se envía a quienes dijeron que no asistirán ni a quienes pidieron no recibir correos.</div>
                    @endif

                    @foreach ([
                        'sin_respuesta' => ['Quienes no han respondido', $porRecordar, $porRecordarWhatsapp, 'Se les pide que confirmen.'],
                        'confirmados' => ['Quienes confirmaron', $confirmadosPorRecordar, $confirmadosPorRecordarWhatsapp, 'Se les recuerda que les esperan.'],
                    ] as $grupo => [$titulo, $nCorreo, $nWhatsapp, $ayuda])
                        @php($n = max($nCorreo, $nWhatsapp))
                        <div class="border border-dashed border-gray-300 rounded p-5 mb-5" data-grupo-recordatorio>
                            <label class="form-check form-check-custom form-check-solid mb-1">
                                <input class="form-check-input" type="checkbox" name="incluir[]" value="{{ $grupo }}" @checked($n > 0) @disabled($n === 0) data-incluir>
                                <span class="form-check-label fw-bold text-gray-900 fs-6">{{ $titulo }}</span>
                                <span class="text-muted fs-7 ms-2">correo: {{ $nCorreo }} · WhatsApp: {{ $nWhatsapp }}</span>
                            </label>
                            <div class="text-muted fs-7 ms-9 mb-4">{{ $ayuda }}</div>
                            <div data-campos @if ($n === 0) hidden @endif>
                                <label class="form-label fw-semibold fs-7">Asunto</label>
                                <input name="{{ $grupo }}[asunto]" value="{{ $textosRecordatorio[$grupo]['asunto'] }}" maxlength="200" class="form-control form-control-sm form-control-solid mb-3">
                                <label class="form-label fw-semibold fs-7">Mensaje</label>
                                <textarea name="{{ $grupo }}[mensaje]" rows="5" maxlength="5000" class="form-control form-control-sm form-control-solid">{{ $textosRecordatorio[$grupo]['mensaje'] }}</textarea>
                                <a href="#" class="btn btn-sm btn-link px-0 mt-2" data-vista-previa="{{ route('invitaciones.vista-previa', [$segmento, $evento]) }}"
                                   data-motivo="recordatorio" data-prefijo="{{ $grupo }}"><i class="ki-outline ki-eye fs-4"></i> Vista previa</a>
                            </div>
                        </div>
                    @endforeach
                    <div class="text-muted fs-8">Variables disponibles: {nombre}, {nombres}, {empresa}, {oficio}, {titulo}, {fecha}, {hora}, {lugar}, {sede}, {academia}.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="canal" value="whatsapp" class="btn btn-success" data-cargando @disabled($porRecordarWhatsapp + $confirmadosPorRecordarWhatsapp === 0)>
                        <i class="ki-outline ki-whatsapp fs-3"></i> Por WhatsApp
                    </button>
                    <button type="submit" name="canal" value="correo" class="btn btn-warning" data-cargando @disabled($porRecordar + $confirmadosPorRecordar === 0)>
                        <i class="ki-outline ki-sms fs-3"></i> Por correo
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                // Cambiar de plantilla rellena asunto y mensaje
                document.querySelectorAll('[data-selector-plantilla]').forEach(function (sel) {
                    sel.addEventListener('change', function () {
                        var op = sel.selectedOptions[0], form = sel.closest('form');
                        form.querySelector('[name="asunto"]').value = op.dataset.asunto;
                        form.querySelector('[name="mensaje"]').value = op.dataset.mensaje;
                    });
                });
                // Vista previa con el texto actual del formulario
                document.querySelectorAll('[data-vista-previa]').forEach(function (btn) {
                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        var form = btn.closest('form');
                        var p = btn.dataset.prefijo;
                        var params = new URLSearchParams({
                            motivo: btn.dataset.motivo,
                            asunto: form.querySelector('[name="' + (p ? p + '[asunto]' : 'asunto') + '"]').value,
                            mensaje: form.querySelector('[name="' + (p ? p + '[mensaje]' : 'mensaje') + '"]').value
                        });
                        window.open(btn.dataset.vistaPrevia + '?' + params.toString(), '_blank');
                    });
                });
                // Recordatorio: mostrar el texto solo de los grupos marcados
                document.querySelectorAll('[data-grupo-recordatorio]').forEach(function (g) {
                    var chk = g.querySelector('[data-incluir]');
                    chk.addEventListener('change', function () { g.querySelector('[data-campos]').hidden = !chk.checked; });
                });
                // Insertar variable en el mensaje
                document.querySelectorAll('[data-variable]').forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        var area = chip.closest('form').querySelector('[name="mensaje"]');
                        var ini = area.selectionStart, fin = area.selectionEnd;
                        area.value = area.value.slice(0, ini) + chip.dataset.variable + area.value.slice(fin);
                        area.focus();
                        area.selectionStart = area.selectionEnd = ini + chip.dataset.variable.length;
                    });
                });
                // Evitar doble envío. Hay un botón por canal: el elegido se pasa en un campo oculto,
                // porque un botón deshabilitado ya no se envía con el formulario.
                document.querySelectorAll('[data-form-correo]').forEach(function (form) {
                    form.addEventListener('submit', function (ev) {
                        var elegido = ev.submitter;
                        if (elegido && elegido.name) {
                            var oculto = document.createElement('input');
                            oculto.type = 'hidden';
                            oculto.name = elegido.name;
                            oculto.value = elegido.value;
                            form.appendChild(oculto);
                        }
                        form.querySelectorAll('[data-cargando]').forEach(function (btn) {
                            btn.disabled = true;
                            if (btn === elegido || !elegido) {
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando…';
                            }
                        });
                    });
                });
            })();
        </script>
    @endpush
@endif
