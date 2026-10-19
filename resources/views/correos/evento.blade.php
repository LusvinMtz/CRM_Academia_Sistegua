@php
    // En el envío real el logo va incrustado en el correo; en la vista previa se usa la URL pública
    $logo = isset($message) && method_exists($message, 'embed')
        ? $message->embed(public_path('assets/media/logos/academia-icon.png'))
        : asset('assets/media/logos/academia-icon.png');
    $cancelado = $motivo === 'cancelacion';
    // Arte del evento: incrustado en el envío real, por URL en la vista previa
    $imagen = null;
    if (! $cancelado && $evento->rutaImagen()) {
        $imagen = isset($message) && method_exists($message, 'embed')
            ? $message->embed($evento->rutaImagen())
            : route('eventos.imagen', [\App\Models\Evento::segmentoDe($evento->tipo), $evento]);
    }
    $etiqueta = [
        'invitacion' => $evento->tipo === 'reunion' ? 'Invitación a reunión' : 'Convocatoria a capacitación',
        'recordatorio' => 'Recordatorio',
        'cambio' => 'Cambio en la actividad',
        'posposicion' => 'Actividad pospuesta',
        'cancelacion' => 'Actividad cancelada',
    ][$motivo] ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $evento->titulo }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f3f8; font-family:Arial, Helvetica, sans-serif; color:#252f4a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f3f8; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden;">
                {{-- Encabezado --}}
                <tr>
                    <td style="background:#d62228; padding:20px 28px;">
                        <table role="presentation" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="background:#ffffff; border-radius:8px; padding:4px;">
                                    <img src="{{ $logo }}" width="44" height="44" alt="{{ config('academia.nombre') }}" style="display:block; border:0;">
                                </td>
                                <td style="padding-left:14px; color:#ffffff;">
                                    <div style="font-size:18px; font-weight:bold;">{{ config('academia.nombre') }}</div>
                                    <div style="font-size:13px; opacity:.85;">Sede {{ $evento->sede->nombre }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <div style="display:inline-block; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px;
                                    color:{{ $cancelado ? '#f8285a' : '#d62228' }}; background:{{ $cancelado ? '#ffeef3' : '#fbe9ea' }};
                                    padding:4px 10px; border-radius:6px; margin-bottom:14px;">{{ $etiqueta }}</div>
                        <h1 style="margin:0 0 18px; font-size:22px; line-height:1.3; color:#071437; {{ $cancelado ? 'text-decoration:line-through;' : '' }}">{{ $evento->titulo }}</h1>

                        <div style="font-size:15px; line-height:1.6; color:#4b5675; white-space:pre-line;">{{ $mensaje }}</div>

                        {{-- Datos del evento --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0; border:1px dashed #dbdfe9; border-radius:10px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    <div style="font-size:12px; color:#99a1b7; text-transform:uppercase; font-weight:bold;">Fecha y hora</div>
                                    <div style="font-size:15px; font-weight:bold; color:#071437; margin-top:2px;">{{ $evento->horario }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 18px 16px;">
                                    @if ($evento->es_virtual)
                                        <div style="font-size:12px; color:#99a1b7; text-transform:uppercase; font-weight:bold;">Modalidad virtual · {{ $evento->plataforma }}</div>
                                        @unless ($cancelado)
                                            <div style="font-size:15px; margin-top:2px;"><a href="{{ $evento->enlace }}" style="color:#d62228; word-break:break-all;">{{ $evento->enlace }}</a></div>
                                        @endunless
                                    @else
                                        <div style="font-size:12px; color:#99a1b7; text-transform:uppercase; font-weight:bold;">Lugar</div>
                                        <div style="font-size:15px; font-weight:bold; color:#071437; margin-top:2px;">{{ $evento->lugar }}</div>
                                    @endif
                                </td>
                            </tr>
                            @if ($evento->facilitador)
                                <tr>
                                    <td style="padding:0 18px 16px;">
                                        <div style="font-size:12px; color:#99a1b7; text-transform:uppercase; font-weight:bold;">Facilitador</div>
                                        <div style="font-size:15px; color:#071437; margin-top:2px;">{{ $evento->facilitador }}</div>
                                    </td>
                                </tr>
                            @endif
                        </table>

                        @if ($conBotones)
                            <div style="font-size:15px; font-weight:bold; color:#071437; margin-bottom:12px;">¿Podrá asistir?</div>
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding:0 10px 10px 0;">
                                        <a href="{{ $urlSi }}" style="display:inline-block; background:#17c653; color:#ffffff; text-decoration:none; font-weight:bold; font-size:15px; padding:12px 22px; border-radius:8px;">Confirmo asistencia</a>
                                    </td>
                                    <td style="padding:0 0 10px;">
                                        <a href="{{ $urlNo }}" style="display:inline-block; background:#f1f1f4; color:#4b5675; text-decoration:none; font-weight:bold; font-size:15px; padding:12px 22px; border-radius:8px;">No podré asistir</a>
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size:13px; color:#99a1b7; margin-top:6px;">Adjuntamos un archivo para agregar la actividad a su calendario.</div>
                        @endif

                        @if ($imagen)
                            <img src="{{ $imagen }}" alt="{{ $evento->titulo }}" width="544" style="display:block; width:100%; max-width:544px; height:auto; border:0; border-radius:10px; margin:24px 0 0;">
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="background:#f9f9fb; padding:18px 28px; font-size:12px; line-height:1.6; color:#99a1b7;">
                        {{ config('academia.nombre') }} — Sede {{ $evento->sede->nombre }}@if ($evento->sede->direccion), {{ $evento->sede->direccion }}@endif.<br>
                        @if ($evento->sede->telefono)Teléfono: {{ $evento->sede->telefono }}. @endif
                        Recibe este correo porque está registrado(a) en la academia.
                        <a href="{{ $urlBaja }}" style="color:#99a1b7;">No deseo recibir más correos</a>.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
