@php
    $esCapacitacion = $config['tipo'] === \App\Models\Evento::CAPACITACION;
    $modalidad = old('modalidad', $evento->modalidad ?? 'presencial');
    $paraTodos = (bool) old('para_todos', $evento->para_todos);
    $gruposMarcados = collect(old('grupos', $evento->relationLoaded('grupos') ? $evento->grupos->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="card">
    <div class="card-body p-lg-10">
        <h4 class="fw-bold text-gray-800 mb-5">Datos generales</h4>
        <div class="row g-6 mb-10">
            <div class="col-md-8">
                <label for="titulo" class="required form-label fw-semibold">Título</label>
                <input id="titulo" name="titulo" value="{{ old('titulo', $evento->titulo) }}" required maxlength="150"
                       class="form-control form-control-solid @error('titulo') is-invalid @enderror"
                       placeholder="{{ $esCapacitacion ? 'Por ejemplo: Evaluación por competencias' : 'Por ejemplo: Entrega de notas del segundo bimestre' }}">
                @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="sede_id" class="required form-label fw-semibold">Sede</label>
                <select id="sede_id" name="sede_id" required class="form-select form-select-solid @error('sede_id') is-invalid @enderror">
                    @if ($sedes->count() > 1)<option value="">Seleccione</option>@endif
                    @foreach ($sedes as $s)
                        <option value="{{ $s->id }}" data-direccion="{{ $s->direccion }}"
                            @selected((string) old('sede_id', $evento->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label for="descripcion" class="form-label fw-semibold">Descripción o agenda</label>
                <textarea id="descripcion" name="descripcion" rows="4" maxlength="5000"
                          class="form-control form-control-solid @error('descripcion') is-invalid @enderror"
                          placeholder="Temas a tratar, qué deben llevar, indicaciones…">{{ old('descripcion', $evento->descripcion) }}</textarea>
                @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Aparecerá en la invitación.</div>
            </div>
        </div>

        <h4 class="fw-bold text-gray-800 mb-5">Fecha y hora</h4>
        <div class="row g-6 mb-10">
            <div class="col-md-4">
                <label for="fecha" class="required form-label fw-semibold">Fecha</label>
                <input id="fecha" type="date" name="fecha" value="{{ old('fecha', $evento->inicio?->format('Y-m-d')) }}" required
                       class="form-control form-control-solid @error('fecha') is-invalid @enderror">
                @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="hora_inicio" class="required form-label fw-semibold">Hora de inicio</label>
                <input id="hora_inicio" type="time" name="hora_inicio" value="{{ old('hora_inicio', $evento->inicio?->format('H:i')) }}" required
                       class="form-control form-control-solid @error('hora_inicio') is-invalid @enderror">
                @error('hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="hora_fin" class="required form-label fw-semibold">Hora de fin</label>
                <input id="hora_fin" type="time" name="hora_fin" value="{{ old('hora_fin', $evento->fin?->format('H:i')) }}" required
                       class="form-control form-control-solid @error('hora_fin') is-invalid @enderror">
                @error('hora_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <h4 class="fw-bold text-gray-800 mb-5">Modalidad</h4>
        <div class="row g-4 mb-6">
            @foreach (['presencial' => ['Presencial', 'En la sede u otro lugar', 'ki-geolocation'], 'virtual' => ['Virtual', 'Zoom, Google Meet o Teams', 'ki-screen']] as $valor => [$etq, $detalle, $icono])
                <div class="col-md-6">
                    <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center text-start p-5 w-100 {{ $modalidad === $valor ? 'active' : '' }}" data-opcion-modalidad>
                        <input type="radio" class="btn-check" name="modalidad" value="{{ $valor }}" @checked($modalidad === $valor)>
                        <i class="ki-outline {{ $icono }} fs-2x me-4"></i>
                        <span class="d-flex flex-column">
                            <span class="fw-bold fs-5 text-gray-900">{{ $etq }}</span>
                            <span class="text-muted fw-semibold fs-7">{{ $detalle }}</span>
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
        <div class="row g-6 mb-10">
            <div class="col-12" data-modalidad="presencial" @if ($modalidad !== 'presencial') hidden @endif>
                <label for="lugar" class="required form-label fw-semibold">Lugar</label>
                <input id="lugar" name="lugar" value="{{ old('lugar', $evento->lugar) }}" maxlength="200"
                       class="form-control form-control-solid @error('lugar') is-invalid @enderror" placeholder="Salón, auditorio y dirección">
                @error('lugar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Al elegir la sede se completa con su dirección. Puede agregar el salón al inicio, por ejemplo "Salón de usos múltiples".</div>
            </div>
            <div class="col-12" data-modalidad="virtual" @if ($modalidad !== 'virtual') hidden @endif>
                <label for="enlace" class="required form-label fw-semibold">Enlace de la reunión virtual</label>
                <input id="enlace" type="url" name="enlace" value="{{ old('enlace', $evento->enlace) }}" maxlength="500"
                       class="form-control form-control-solid @error('enlace') is-invalid @enderror" placeholder="https://meet.google.com/abc-defg-hij">
                @error('enlace') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        @if ($esCapacitacion)
            <h4 class="fw-bold text-gray-800 mb-5">Capacitación</h4>
            <div class="row g-6 mb-10">
                @php($disenos = \App\Models\PlantillaCertificado::orderByDesc('predeterminada')->orderBy('nombre')->get())
                <div class="{{ $disenos->isNotEmpty() ? 'col-md-6' : 'col-12' }}">
                    <label for="facilitador" class="form-label fw-semibold">Facilitador o capacitador</label>
                    <input id="facilitador" name="facilitador" value="{{ old('facilitador', $evento->facilitador) }}" maxlength="150"
                           class="form-control form-control-solid @error('facilitador') is-invalid @enderror" placeholder="Nombre de quien imparte la capacitación">
                    @error('facilitador') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                {{-- Cupo máximo: oculto por ahora. Sin este campo las capacitaciones se guardan sin límite.
                <div class="col-md-4">
                    <label for="cupo" class="form-label fw-semibold">Cupo máximo</label>
                    <input id="cupo" type="number" name="cupo" min="1" max="5000" value="{{ old('cupo', $evento->cupo) }}"
                           class="form-control form-control-solid @error('cupo') is-invalid @enderror" placeholder="Sin límite">
                    @error('cupo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                --}}
                @if ($disenos->isNotEmpty())
                    <div class="col-md-6">
                        <label for="plantilla_certificado_id" class="form-label fw-semibold">Diseño del certificado</label>
                        <select id="plantilla_certificado_id" name="plantilla_certificado_id" class="form-select form-select-solid">
                            <option value="">Usar el diseño predeterminado</option>
                            @foreach ($disenos as $d)
                                <option value="{{ $d->id }}" @selected((string) old('plantilla_certificado_id', $evento->plantilla_certificado_id) === (string) $d->id)>
                                    {{ $d->nombre }}@if ($d->predeterminada) (predeterminado)@endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Se usa en las constancias que se entregan al finalizar.</div>
                    </div>
                @endif
            </div>
        @endif

        <h4 class="fw-bold text-gray-800 mb-2">¿A quién va dirigida?</h4>
        <div class="text-muted fs-7 mb-5">Estas personas recibirán la invitación por correo (fase 4).</div>
        @error('grupos')
            <div class="alert bg-light-danger border border-danger border-dashed p-4 mb-5 fw-semibold text-gray-800">{{ $message }}</div>
        @enderror
        <input type="hidden" name="para_todos" value="0">
        <label class="form-check form-check-custom form-check-solid mb-5">
            <input class="form-check-input" type="checkbox" name="para_todos" value="1" id="para_todos" @checked($paraTodos)>
            <span class="form-check-label fw-semibold text-gray-800">Todos los {{ $config['publico_texto'] }} de la sede</span>
        </label>
        <div id="lista-grupos" @if ($paraTodos) hidden @endif>
            @if ($grupos->isEmpty())
                <div class="text-muted fs-7">
                    No hay grupos de {{ $config['publico_texto'] }}.
                    <a href="{{ route('grupos.create', ['tipo' => $config['publico']]) }}">Crear un grupo</a>
                </div>
            @else
                <div class="text-gray-700 fs-7 mb-3">O elija uno o más grupos:</div>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($grupos as $g)
                        <label class="form-check form-check-sm form-check-custom form-check-solid border border-dashed border-gray-300 rounded px-3 py-2"
                               @if ($g->sede) data-grupo-sede="{{ $g->sede_id }}" @endif>
                            <input class="form-check-input" type="checkbox" name="grupos[]" value="{{ $g->id }}" @checked(in_array($g->id, $gruposMarcados))>
                            <span class="form-check-label text-gray-700">
                                {{ $g->nombre }} <span class="text-muted fs-8">({{ $g->contactos_count }}{{ $g->sede ? ' · '.$g->sede->nombre : '' }})</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="form-text">De los grupos se toman solo las personas de la sede del evento.</div>
            @endif
        </div>

        @php($conf = config('academia.recordatorio'))
        {{-- Solo visible si el envío automático está activado en el servidor; si no, se usa el botón de la ficha --}}
        <div class="bg-light rounded p-5 mt-8" @unless ($conf['automatico']) hidden @endunless>
            <input type="hidden" name="recordatorio_automatico" value="0">
            <label class="form-check form-switch form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="recordatorio_automatico" value="1"
                       @checked(old('recordatorio_automatico', $evento->recordatorio_automatico ?? true))>
                <span class="form-check-label fw-semibold text-gray-800">
                    Enviar recordatorio automático {{ $conf['dias_antes'] === 1 ? 'un día antes' : $conf['dias_antes'].' días antes' }}, a las {{ $conf['hora'] }}
                    <span class="d-block text-muted fs-7 fw-normal">A quienes confirmaron ("le esperamos") y a quienes no han respondido. No se envía a quienes dijeron que no asistirán.</span>
                </span>
            </label>
        </div>
    </div>
    @if ($evento->exists && $evento->tieneInvitacionesEnviadas())
        <div class="px-lg-10 px-6 pb-6">
            <div class="bg-light-primary rounded p-5 d-flex gap-3">
                <i class="ki-outline ki-sms fs-2 text-primary"></i>
                <div class="fw-semibold text-gray-800">
                    Ya se enviaron invitaciones: si cambia la fecha, la hora o el lugar, se avisará por correo a los invitados.
                    <span class="d-block text-muted fs-7 fw-normal">No se avisa a quienes ya dijeron que no asistirán. Si solo corrige el título o la descripción, no se envía nada.</span>
                </div>
            </div>
        </div>
    @endif
    <div class="card-footer d-flex justify-content-end gap-3 py-6">
        <a href="{{ $evento->exists ? route('eventos.show', [$segmento, $evento]) : route('eventos.index', $segmento) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $evento->exists ? 'Guardar cambios' : 'Programar '.$config['singular'] }}</button>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var sede = document.getElementById('sede_id');
            var lugar = document.getElementById('lugar');
            var direccionAnterior = sede.selectedOptions[0] ? (sede.selectedOptions[0].dataset.direccion || '') : '';

            // Al cambiar de sede, el lugar toma la dirección de la nueva sede (si no se había escrito otro)
            sede.addEventListener('change', function () {
                var nueva = sede.selectedOptions[0] ? (sede.selectedOptions[0].dataset.direccion || '') : '';
                if (!lugar.value.trim() || lugar.value === direccionAnterior) lugar.value = nueva;
                direccionAnterior = nueva;
                filtrarGrupos();
            });

            // Presencial / virtual
            document.querySelectorAll('input[name="modalidad"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.querySelectorAll('[data-opcion-modalidad]').forEach(function (l) {
                        l.classList.toggle('active', l.querySelector('input').checked);
                    });
                    document.querySelectorAll('[data-modalidad]').forEach(function (bloque) {
                        bloque.hidden = bloque.dataset.modalidad !== radio.value;
                    });
                });
            });

            // Todos / grupos
            var todos = document.getElementById('para_todos');
            todos.addEventListener('change', function () { document.getElementById('lista-grupos').hidden = todos.checked; });

            function filtrarGrupos() {
                document.querySelectorAll('[data-grupo-sede]').forEach(function (el) {
                    var visible = !sede.value || el.dataset.grupoSede === sede.value;
                    el.classList.toggle('d-none', !visible);
                    if (!visible) el.querySelector('input').checked = false;
                });
            }
            filtrarGrupos();
        })();
    </script>
@endpush
