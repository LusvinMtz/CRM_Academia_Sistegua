<?php

namespace App\Jobs;

use App\Models\Invitacion;
use App\Services\ErrorWhatsApp;
use App\Services\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Envía por WhatsApp un mensaje de un evento (o la constancia en PDF) a un invitado
 * y registra el resultado en su invitación. Un error no detiene el resto de envíos.
 */
class EnviarWhatsAppEvento implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Con el límite de envíos de WasenderAPI el mensaje vuelve a la cola varias veces antes de darse por fallido. */
    public int $tries = 10;

    public function __construct(
        public Invitacion $invitacion,
        public string $motivo,
        public string $mensaje,
    ) {
    }

    public function handle(): void
    {
        $inv = $this->invitacion->load(['evento.sede', 'contacto']);
        $telefono = $inv->telefono ?? $inv->contacto->telefono;

        try {
            $whatsapp = new WhatsApp();
            if ($this->motivo === 'constancia') {
                $whatsapp->documento(
                    $telefono,
                    URL::temporarySignedRoute('constancia.publica', now()->addDays(30), ['token' => $inv->token]),
                    'constancia-'.str($inv->contacto->nombre_completo)->slug().'.pdf',
                    $this->mensaje,
                );
            } else {
                $whatsapp->texto($telefono, $this->texto($inv));
            }
        } catch (ErrorWhatsApp $e) {
            // Límite de envíos: se reintenta cuando WasenderAPI lo indique (solo si hay una cola real)
            if ($e->esperar > 0 && $this->job && ! $this->job instanceof SyncJob && $this->attempts() < $this->tries) {
                $this->release($e->esperar);

                return;
            }
            report($e);
            if ($this->motivo === 'invitacion') {
                $inv->marcarCanal(Invitacion::WHATSAPP, Invitacion::FALLIDA, Str::limit($e->getMessage(), 450));
            }

            return;
        }

        match ($this->motivo) {
            'invitacion' => $inv->marcarCanal(Invitacion::WHATSAPP, Invitacion::ENVIADA),
            'recordatorio' => $inv->update(['recordatorio_at' => now()]),
            default => null,
        };
    }

    /** Mensaje con los datos del evento y el enlace para responder (WhatsApp usa *negritas*). */
    private function texto(Invitacion $inv): string
    {
        $evento = $inv->evento;
        $lineas = [
            '*'.config('academia.nombre').'* — Sede '.$evento->sede->nombre,
            '*'.$evento->titulo.'*',
            '',
            trim($this->mensaje),
            '',
            '📅 '.$evento->horario,
            $evento->es_virtual ? "💻 Virtual ({$evento->plataforma}): {$evento->enlace}" : '📍 '.$evento->lugar,
        ];
        if ($evento->facilitador) {
            $lineas[] = '👤 Facilitador: '.$evento->facilitador;
        }
        if ($this->motivo !== 'cancelacion' && $evento->acepta_respuestas) {
            $lineas[] = '';
            $lineas[] = 'Confirme su asistencia aquí: '.$inv->url();
        }

        return implode("\n", $lineas);
    }
}
