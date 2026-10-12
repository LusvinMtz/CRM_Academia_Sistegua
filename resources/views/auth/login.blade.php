<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/media/logos/academia-icon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/plugins/keenicons/outline/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <script>
        try { const t = localStorage.getItem('tema'); if (t) document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
    </script>
</head>
<body class="app-blank">
<div class="d-flex flex-column flex-root" id="kt_app_root">
    <div class="d-flex flex-column flex-lg-row flex-column-fluid">

        <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
            <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                <div class="w-100 w-sm-400px p-10">
                    <form method="POST" action="{{ route('login.store') }}" class="form w-100" novalidate>
                        @csrf
                        <div class="text-center mb-11">
                            <h1 class="text-gray-900 fw-bolder mb-3">Iniciar sesión</h1>
                            <div class="text-gray-500 fw-semibold fs-6">{{ config('academia.nombre') }}</div>
                        </div>

                        @if ($errors->any())
                            <div class="alert bg-light-danger border border-danger border-dashed d-flex align-items-center p-4 mb-8">
                                <i class="ki-outline ki-information fs-2hx text-danger me-3"></i>
                                <span class="fw-semibold text-gray-800">{{ $errors->first() }}</span>
                            </div>
                        @endif

                        <div class="fv-row mb-8">
                            <label for="email" class="form-label fw-semibold text-gray-900">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required
                                   class="form-control bg-transparent @error('email') is-invalid @enderror" placeholder="usuario@correo.com">
                        </div>

                        <div class="fv-row mb-10">
                            <label for="password" class="form-label fw-semibold text-gray-900">Contraseña</label>
                            <div class="position-relative">
                                <input id="password" type="password" name="password" autocomplete="current-password" required
                                       class="form-control bg-transparent pe-12" placeholder="••••••••">
                                <button type="button" class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                                        data-ver-password="password" aria-label="Mostrar contraseña">
                                    <i class="ki-outline ki-eye fs-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid mb-10">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="d-flex flex-center flex-wrap px-5">
                <span class="text-muted fw-semibold fs-7">{{ config('academia.nombre') }} © {{ now()->year }}</span>
            </div>
        </div>

        <div class="d-flex flex-lg-row-fluid w-lg-50 auth-aside order-1 order-lg-2">
            {{-- Fondo decorativo animado: figuras con la forma redondeada del logo --}}
            <div class="auth-fondo" aria-hidden="true">
                <span class="auth-brillo"></span>
                <span class="figura f1"></span>
                <span class="figura f2"></span>
                <span class="figura f3"></span>
                <span class="figura f4"></span>
                <span class="figura f5"></span>
                <span class="figura f6"></span>
                <span class="figura f7"></span>
            </div>
            <div class="auth-contenido d-flex flex-column flex-center py-10 py-lg-15 px-5 px-md-15 w-100">
                <div class="bg-white rounded-4 shadow-sm p-5 p-lg-7 mb-8 mb-lg-10">
                    <img alt="{{ config('academia.nombre') }}" src="{{ asset('assets/media/logos/academia-logo.png') }}" class="logo-login">
                </div>
                <h1 class="text-white fs-2qx fw-bolder text-center mb-4">{{ config('academia.nombre') }}</h1>
                <div class="text-white fs-base text-center opacity-75">
                    Reuniones con clientes y capacitaciones para clientes.
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
