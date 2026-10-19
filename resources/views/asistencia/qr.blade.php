@extends('layouts.app')

@section('title', 'QR de asistencia')
@section('acciones')
    <a href="{{ route('eventos.show', [$segmento, $evento]) }}" class="btn btn-sm text-white" style="background-color: #f97316; border-color: #f97316;"><i class="ki-outline ki-arrow-left fs-2 text-white"></i> Regresar</a>
    <a href="{{ route('asistencia.qr.png', [$segmento, $evento]) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-file-down fs-2"></i> Descargar imagen</a>
    <button type="button" class="btn btn-sm btn-light" onclick="window.print()"><i class="ki-outline ki-printer fs-2"></i> Imprimir</button>
    <button type="button" class="btn btn-sm btn-primary" data-pantalla-completa><i class="ki-outline ki-maximize fs-2"></i> Proyectar</button>
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-7">
            <div class="card h-100" id="cartel-qr">
                <div class="card-body d-flex flex-column align-items-center text-center p-10">
                    <img src="{{ asset('assets/media/logos/academia-logo.png') }}" alt="" class="h-70px mb-5">
                    <div class="text-muted fw-semibold fs-6 text-uppercase mb-1">Registro de asistencia</div>
                    <h2 class="fs-1 fw-bold text-gray-900 mb-2">{{ $evento->titulo }}</h2>
                    <div class="text-gray-600 fs-6 mb-6">{{ $evento->horario }} · Sede {{ $evento->sede->nombre }}</div>
                    <img src="{{ $qr }}" alt="Código QR para registrar la asistencia" class="qr-grande mb-6">
                    <div class="fs-3 fw-bold text-gray-900 mb-2">Escanee con la cámara de su celular</div>
                    <div class="text-gray-600 fs-6">y escriba su correo o su DPI para registrar su asistencia.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-5 no-imprimir">
            <div class="card mb-5">
                <div class="card-header border-0 pt-6"><h3 class="card-title fw-bold">Cómo usarlo</h3></div>
                <div class="card-body pt-2 text-gray-700 fs-6">
                    <ol class="ps-5 mb-0">
                        <li class="mb-3"><strong>Proyecte</strong> esta pantalla en el salón o <strong>imprima</strong> el cartel y colóquelo en la entrada.</li>
                        <li class="mb-3">Cada cliente escanea el código y escribe su <strong>correo o DPI</strong>; su asistencia queda registrada al instante.</li>
                        <li class="mb-3">Quien no tenga celular o no esté registrado puede anotarse en la <a href="{{ route('asistencia.show', [$segmento, $evento]) }}">lista de asistencia</a>.</li>
                        <li>El registro funciona desde {{ \App\Models\Evento::REGISTRO_ANTES }} minutos antes del inicio hasta {{ \App\Models\Evento::REGISTRO_DESPUES }} minutos después del final.</li>
                    </ol>
                </div>
            </div>
            <div class="card mb-5">
                <div class="card-body d-flex align-items-center gap-4">
                    <i class="ki-outline ki-scan-barcode fs-2x text-primary"></i>
                    <div class="text-gray-700 fs-7">
                        <strong class="text-gray-900">También puede escanear el QR personal</strong> que cada invitado recibe en su correo:
                        ábralo con la cámara de su celular (con su sesión iniciada en el sistema) y la asistencia se marca sola.
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="fw-semibold text-gray-700 me-2">Estado del registro:</span>
                        @if ($evento->registro_abierto)
                            <span class="badge badge-light-success">Abierto ahora</span>
                        @elseif ($evento->cancelado_at)
                            <span class="badge badge-light-danger">Evento anulado</span>
                        @elseif ($evento->inicio->isFuture())
                            <span class="badge badge-light-warning">Abre el {{ $evento->inicio->copy()->subMinutes(\App\Models\Evento::REGISTRO_ANTES)->translatedFormat('j \d\e F \a \l\a\s H:i') }}</span>
                        @else
                            <span class="badge badge-light">Cerrado</span>
                        @endif
                    </div>
                    <div class="text-muted fs-8 text-break mb-4">{{ $evento->urlRegistro() }}</div>
                    <form method="POST" action="{{ route('asistencia.qr.renovar', [$segmento, $evento]) }}"
                          data-confirmar="¿Generar un QR nuevo? El QR impreso o compartido anteriormente dejará de funcionar.">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light-danger">Generar un QR nuevo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .qr-grande { width: min(420px, 80vw); height: auto; image-rendering: pixelated; }
        #cartel-qr:fullscreen { display: flex; align-items: center; justify-content: center; background: #fff; }
        #cartel-qr:fullscreen .qr-grande { width: min(62vh, 80vw); }
        @media print {
            #kt_app_header, #kt_app_sidebar, #kt_app_toolbar, #kt_app_footer, .no-imprimir { display: none !important; }
            #kt_app_wrapper, #kt_app_main { margin: 0 !important; padding: 0 !important; }
            .col-xl-7 { width: 100% !important; }
            #cartel-qr { box-shadow: none !important; border: 0 !important; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelector('[data-pantalla-completa]').addEventListener('click', function () {
            var el = document.getElementById('cartel-qr');
            if (el.requestFullscreen) el.requestFullscreen();
        });
    </script>
@endpush
