@extends('layouts.app')

@section('title', 'Configuración de correo')

@section('content')
    @php($puedeEditar = auth()->user()->can('correo.editar'))
    <div class="row g-5 g-xl-8">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('correo.update') }}" class="card h-100" novalidate autocomplete="off">
                @csrf @method('PUT')
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Cuenta de envío</h3>
                    <div class="card-toolbar">
                        @if ($config->modo === \App\Models\ConfiguracionCorreo::SMTP)
                            <span class="badge badge-light-success fw-bold">Envío real</span>
                        @else
                            <span class="badge badge-light-warning fw-bold">Modo de prueba</span>
                        @endif
                    </div>
                </div>
                <fieldset class="card-body pt-2" @disabled(! $puedeEditar)>
                    <div class="row g-6">
                        <div class="col-12">
                            <label for="modo" class="required form-label fw-semibold">Modo</label>
                            <select id="modo" name="modo" class="form-select form-select-solid">
                                @foreach (\App\Models\ConfiguracionCorreo::MODOS as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('modo', $config->modo) === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="remitente_correo" class="required form-label fw-semibold">Correo del remitente</label>
                            <input id="remitente_correo" type="email" name="remitente_correo" value="{{ old('remitente_correo', $config->remitente_correo) }}"
                                   class="form-control form-control-solid @error('remitente_correo') is-invalid @enderror">
                            @error('remitente_correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Desde esta dirección salen todas las invitaciones, recordatorios y constancias.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="remitente_nombre" class="required form-label fw-semibold">Nombre del remitente</label>
                            <input id="remitente_nombre" name="remitente_nombre" value="{{ old('remitente_nombre', $config->remitente_nombre) }}" maxlength="100"
                                   class="form-control form-control-solid @error('remitente_nombre') is-invalid @enderror">
                            @error('remitente_nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Es lo que ve la persona en su bandeja de entrada.</div>
                        </div>

                        <div class="col-12" data-solo-smtp>
                            <div class="separator separator-dashed my-2"></div>
                        </div>
                        <div class="col-12" data-solo-smtp>
                            <span class="form-label fw-semibold d-block mb-2">Llenar con un proveedor</span>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (\App\Models\ConfiguracionCorreo::PROVEEDORES as $nombre => $p)
                                    <button type="button" class="btn btn-sm btn-light" data-proveedor='@json($p)'>{{ $nombre }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6" data-solo-smtp>
                            <label for="host" class="required form-label fw-semibold">Servidor SMTP</label>
                            <input id="host" name="host" value="{{ old('host', $config->host) }}" placeholder="smtp.gmail.com"
                                   class="form-control form-control-solid @error('host') is-invalid @enderror">
                            @error('host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2 col-5" data-solo-smtp>
                            <label for="puerto" class="required form-label fw-semibold">Puerto</label>
                            <input id="puerto" type="number" name="puerto" value="{{ old('puerto', $config->puerto) }}" min="1" max="65535"
                                   class="form-control form-control-solid @error('puerto') is-invalid @enderror">
                            @error('puerto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 col-7" data-solo-smtp>
                            <label for="cifrado" class="required form-label fw-semibold">Cifrado</label>
                            <select id="cifrado" name="cifrado" class="form-select form-select-solid">
                                @foreach (\App\Models\ConfiguracionCorreo::CIFRADOS as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('cifrado', $config->cifrado) === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" data-solo-smtp>
                            <label for="usuario" class="form-label fw-semibold">Usuario</label>
                            <input id="usuario" name="usuario" value="{{ old('usuario', $config->usuario) }}" autocomplete="off"
                                   class="form-control form-control-solid @error('usuario') is-invalid @enderror">
                            @error('usuario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">En Gmail y Outlook es el mismo correo.</div>
                        </div>
                        <div class="col-md-6" data-solo-smtp>
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <input id="password" type="password" name="password" autocomplete="new-password"
                                   placeholder="{{ $config->tienePassword() ? '•••••••• (guardada)' : '' }}"
                                   class="form-control form-control-solid @error('password') is-invalid @enderror">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Déjela en blanco para conservar la actual. Se guarda cifrada.</div>
                        </div>
                    </div>
                </fieldset>
                @if ($puedeEditar)
                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-3 py-6">
                        <span class="text-muted fs-7">
                            @if ($config->exists)
                                Última modificación: {{ $config->updated_at->format('d/m/Y H:i') }}{{ $config->actualizadoPor ? ' por '.$config->actualizadoPor->name : '' }}
                            @else
                                Todavía se usan los valores del archivo .env.
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
                <form method="POST" action="{{ route('correo.probar') }}" class="card" novalidate>
                    @csrf
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title fw-bold">Correo de prueba</h3>
                    </div>
                    <div class="card-body pt-2">
                        <label for="destino" class="required form-label fw-semibold">Enviar a</label>
                        <input id="destino" type="email" name="destino" value="{{ old('destino', auth()->user()->email) }}"
                               class="form-control form-control-solid @error('destino') is-invalid @enderror">
                        @error('destino') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Usa la configuración guardada. Guarde primero si hizo cambios.</div>
                    </div>
                    <div class="card-footer d-flex justify-content-end py-6">
                        <button type="submit" class="btn btn-light-primary"><i class="ki-outline ki-send fs-4 me-1"></i>Enviar prueba</button>
                    </div>
                </form>
            @endif

            <div class="card">
                <div class="card-body">
                    <h4 class="fw-bold mb-4"><i class="ki-outline ki-information-5 fs-2 text-primary me-2"></i>Gmail</h4>
                    <ol class="text-gray-700 ps-5 mb-4">
                        <li>Active la verificación en dos pasos en la cuenta de Google.</li>
                        <li>Cree una <strong>contraseña de aplicación</strong> en myaccount.google.com/apppasswords.</li>
                        <li>Pegue esa clave de 16 letras aquí (con o sin espacios).</li>
                    </ol>
                    <p class="text-gray-600 fs-7 mb-0">
                        Una cuenta de Gmail envía unos 500 correos por día. Para envíos grandes conviene Brevo, Amazon SES o Mailgun.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var modo = document.getElementById('modo');
            function mostrar() {
                document.querySelectorAll('[data-solo-smtp]').forEach(function (el) {
                    el.hidden = modo.value !== 'smtp';
                });
            }
            modo.addEventListener('change', mostrar);
            mostrar();

            // Resalta el proveedor cuyo servidor coincide con el escrito (también al abrir la página)
            var host = document.getElementById('host');
            var botones = document.querySelectorAll('[data-proveedor]');
            function resaltar() {
                botones.forEach(function (btn) {
                    var activo = JSON.parse(btn.dataset.proveedor).host === host.value.trim().toLowerCase();
                    btn.classList.toggle('btn-primary', activo);
                    btn.classList.toggle('btn-light', !activo);
                    btn.setAttribute('aria-pressed', activo);
                });
            }
            botones.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var p = JSON.parse(btn.dataset.proveedor);
                    host.value = p.host;
                    document.getElementById('puerto').value = p.puerto;
                    document.getElementById('cifrado').value = p.cifrado;
                    resaltar();
                });
            });
            host.addEventListener('input', resaltar);
            resaltar();
        })();
    </script>
@endpush
