<?php

namespace App\Mail;

use App\Http\Controllers\EventoController;
use App\Models\Invitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de un evento para un invitado: invitación, recordatorio, aviso de cambio o de cancelación.
 */
class CorreoEvento extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitacion $invitacion,
        public string $motivo,
        public string $asuntoFinal,
        public string $mensajeFinal,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asuntoFinal);
    }

    public function content(): Content
    {
        $evento = $this->invitacion->evento;

        return new Content(
            view: 'correos.evento',
            text: 'correos.evento_texto',
            with: [
                'evento' => $evento,
                'contacto' => $this->invitacion->contacto,
                'mensaje' => $this->mensajeFinal,
                'motivo' => $this->motivo,
                'conBotones' => $this->motivo !== 'cancelacion' && $evento->acepta_respuestas,
                'urlSi' => $this->invitacion->url('si', Invitacion::CORREO),
                'urlNo' => $this->invitacion->url('no', Invitacion::CORREO),
                'urlBaja' => route('invitacion.baja', $this->invitacion->token),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => EventoController::ics($this->invitacion->evento), 'evento.ics')
                ->withMime('text/calendar; charset=utf-8'),
        ];
    }
}
