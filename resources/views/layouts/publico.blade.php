<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('academia.nombre') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/media/logos/academia-icon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/plugins/keenicons/outline/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="bg-light">
<div class="d-flex flex-column min-vh-100">
    <header class="bg-primary py-5">
        <div class="container mw-650px d-flex align-items-center gap-3">
            <span class="bg-white rounded p-1 d-inline-flex">
                <img src="{{ asset('assets/media/logos/academia-icon.png') }}" alt="" class="h-40px w-40px">
            </span>
            <div class="text-white">
                <div class="fw-bold fs-4 lh-sm">{{ config('academia.nombre') }}</div>
                @hasSection('sede')<div class="fs-7 opacity-75">@yield('sede')</div>@endif
            </div>
        </div>
    </header>
    <main class="flex-grow-1 py-8">
        <div class="container mw-650px">
            @yield('content')
        </div>
    </main>
    <footer class="py-5 text-center text-muted fs-8">
        {{ config('academia.nombre') }}
    </footer>
</div>
</body>
</html>
