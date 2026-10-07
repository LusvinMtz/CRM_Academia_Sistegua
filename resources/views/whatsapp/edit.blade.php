@extends('layouts.app')

@section('title', 'Configuración de WhatsApp')

@section('content')
    @php($puedeEditar = auth()->user()->can('correo.editar'))
    <div class="row g-5 g-xl-8">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('whatsapp.update') }}" class="card h-100" novalidate autocomplete="off">
                @csrf @method('PUT')
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Cuenta de WasenderAPI</h3>
                    <div class="card-toolbar">
                        @if ($config->enModoPrueba())
                            <span class="badge badge-light-warning fw-bold">Modo de prueba</span>
                        @else
                            <span class="badge badge-light-success fw-bold">Envío real</span>
                        @endif
                    </div>
                </div>
                <fieldset class="card-body pt-2" @disabled(! $puedeEditar)>
                    @if ($sesion !== null)
                        <div class="alert {{ $sesion === 'connected' ? 'bg-light-success border-success' : 'bg-light-warning border-warning' }} border border-dashed d-flex align-items-center p-4 mb-6">
                            <i class="ki-outline {{ $sesion === 'connected' ? 'ki-check-circle text-success' : 'ki-information-5 text-warning' }} fs-2x me-3"></i>
                            <span class="fw-semibold text-gray-800 fs-7">
                                Estado de la sesión de WhatsApp: <strong>{{ $sesion === 'connected' ? 'conectada' : $sesion }}</strong>
                                @if ($sesion !== 'connected')
                                    — Entre a wasenderapi.com y vuelva a vincular el teléfono escaneando el QR.
                                @endif
                            </span>
                        </div>
                    @endif
                    @if ($enEspera > 0)
                        <div class="alert bg-light-danger border border-danger border-dashed d-flex align-items-center p-4 mb-6">
                            <i class="ki-outline ki-time fs-2x text-danger me-3"></i>
                            <span class="fw-semibold text-gray-800 fs-7">
                                Hay {{ $enEspera }} {{ $enEspera === 1 ? 'envío atrasado' : 'envíos atrasados' }}. El sistema los reanuda solo
                                (al abrir esta página se vuelven a poner en marcha). Si el aviso sigue después de unos minutos, revise <code>storage/logs/laravel.log</code>.
                            </span>
                        </div>
                    @endif
                    @if ($colaSincrona && ! $config->enModoPrueba())
                        <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-center p-4 mb-6">
                            <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
                            <span class="fw-semibold text-gray-800 fs-7">
                                Los envíos se hacen sin cola (<code>QUEUE_CONNECTION=sync</code>), así que la pausa entre mensajes no se respeta y
                                WasenderAPI puede rechazar los que superen su límite. Para envíos a muchas personas use
                                <code>QUEUE_CONNECTION=database</code> y deje corriendo <code>php artisan queue:work</code>.
                            </span>
                        </div>
                    @endif
                    <div class="row g-6">
                        <div class="col-12">
                            <label for="modo" class="required form-label fw-semibold">Modo</label>
                            <select id="modo" name="modo" class="form-select form-select-solid">
                                @foreach (\App\Models\ConfiguracionWhatsapp::MODOS as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('modo', $config->modo) === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="token" class="form-label fw-semibold">Token de acceso (API Key)</label>
                            <input id="token" type="password" name="token" autocomplete="new-password"
                                   placeholder="{{ $config->tieneToken() ? '•••••••• (guardado)' : '' }}"
                                   class="form-control form-control-solid @error('token') is-invalid @enderror">
                            @error('token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Es el API Key de la sesión de WhatsApp en wasenderapi.com. Déjelo en blanco para conservar el actual. Se guarda cifrado.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="codigo_pais" class="required form-label fw-semibold">Código de país</label>
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">+</span>
                                <input id="codigo_pais" name="codigo_pais" value="{{ old('codigo_pais', $config->codigo_pais) }}" maxlength="4" inputmode="numeric"
                                       class="form-control form-control-solid @error('codigo_pais') is-invalid @enderror">
                            </div>
                            @error('codigo_pais') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">Se antepone a los teléfonos de 8 dígitos (Guatemala: 502).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="pausa_segundos" class="required form-label fw-semibold">Pausa entre mensajes (segundos)</label>
                            <input id="pausa_segundos" type="number" name="pausa_segundos" value="{{ old('pausa_segundos', $config->pausa_segundos) }}" min="0" max="600"
                                   class="form-control form-control-solid @error('pausa_segundos') is-invalid @enderror">
                            @error('pausa_segundos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Recomendado: 8. En el plan de prueba de WasenderAPI: 60.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-check form-switch form-check-custom form-check-solid align-items-start">
                                <input type="hidden" name="proteccion" value="0">
                                <input class="form-check-input mt-1" type="checkbox" name="proteccion" value="1" @checked(old('proteccion', $config->proteccion))>
                                <span class="form-check-label">
                                    <span class="fw-bold text-gray-900 d-block">Protección contra bloqueos (recomendada)</span>
                                    <span class="text-muted fs-7">
                                        Nunca menos de 5 segundos entre mensajes y una espera al azar entre la pausa y el doble
                                        (con 8: entre 8 y 16 segundos), para que WhatsApp no lo tome como envío automático o spam.
                                        Los mensajes de todos los usuarios salen en una sola fila, aunque se envíen a la vez.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </fieldset>
                @if ($puedeEditar)
                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-3 py-6">
                        <span class="text-muted fs-7">
                            @if ($config->exists)
                                Última modificación: {{ $config->updated_at->format('d/m/Y H:i') }}{{ $config->actualizadoPor ? ' por '.$config->actualizadoPor->name : '' }}
                            @else
                                Todavía no se ha configurado.
                            @endif
                        </span>
                        <div class="d-flex gap-3">
                            <a href="{{ route('dashboard') }}" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar configuración</button>
                        </div>
                    </div>
                @endif
            </form>
        </div>

        <div class="col-xl-4 d-flex flex-column gap-5 gap-xl-8">
            @if ($puedeEditar)
                <form method="POST" action="{{ route('whatsapp.probar') }}" class="card" novalidate>
                    @csrf
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title fw-bold">Mensaje de prueba</h3>
                    </div>
                    <div class="card-body pt-2">
                        <label for="destino" class="required form-label fw-semibold">Enviar al número</label>
                        <input id="destino" name="destino" value="{{ old('destino', auth()->user()->telefono) }}" placeholder="5874-3210"
                               class="form-control form-control-solid @error('destino') is-invalid @enderror">
                        @error('destino') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Usa la configuración guardada. Guarde primero si hizo cambios.</div>
                    </div>
                    <div class="card-footer d-flex justify-content-end py-6">
                        <button type="submit" class="btn btn-light-success"><i class="ki-outline ki-whatsapp fs-4 me-1"></i>Enviar prueba</button>
                    </div>
                </form>
            @endif

            <div class="card">
                <div class="card-body">
                    <h4 class="fw-bold mb-4"><i class="ki-outline ki-information-5 fs-2 text-primary me-2"></i>Cómo obtener el token</h4>
                    <ol class="text-gray-700 ps-5 mb-4">
                        <li>Cree una cuenta en wasenderapi.com y agregue una sesión de WhatsApp.</li>
                        <li>Vincule el teléfono de la academia escaneando el QR desde WhatsApp → Dispositivos vinculados.</li>
                        <li>Copie el <strong>API Key</strong> de esa sesión y péguelo aquí.</li>
                        <li>En la configuración de la sesión active <strong>Account Protection</strong>: WasenderAPI limitará a 1 mensaje cada 5 segundos.</li>
                    </ol>
                    <p class="text-gray-600 fs-7">
                        Para evitar bloqueos de WhatsApp también ayuda que el número tenga historial de uso normal, que los mensajes
                        lleven el nombre de cada persona (variable <code>{nombre}</code>) y no enviar a quienes pidieron no recibir mensajes.
                    </p>
                    <p class="text-gray-600 fs-7 mb-0">
                        Los mensajes llevan el enlace para confirmar asistencia, así que <code>APP_URL</code> debe ser la dirección pública del sistema.
                        Las constancias también necesitan esa dirección: WasenderAPI descarga el PDF desde ahí.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
