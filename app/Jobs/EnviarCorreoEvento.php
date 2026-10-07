<?php

namespace App\Jobs;

use App\Mail\CorreoEvento;
use App\Models\Invitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Envía un correo de un evento a un invitado y registra el resultado en su invitación.
 * Un error se guarda en la invitación y no detiene el resto de envíos.
 */
class EnviarCorreoEvento implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(
        public Invitacion $invitacion,
        public string $motivo,
        public string $asunto,
        public string $mensaje,
    ) {
    }

    public function handle(): void
    {
        $inv = $this->invitacion->load(['evento.sede', 'contacto']);

        try {
            Mail::to($inv->correo, $inv->contacto->nombre_completo)
                ->send(new CorreoEvento($inv, $this->motivo, $this->asunto, $this->mensaje));
        } catch (\Throwable $e) {
            report($e);
            if ($this->motivo === 'invitacion') {
                $inv->marcarCanal(Invitacion::CORREO, Invitacion::FALLIDA, Str::limit($e->getMessage(), 450));
            }

            return;
        }

        match ($this->motivo) {
            'invitacion' => $inv->marcarCanal(Invitacion::CORREO, Invitacion::ENVIADA),
            'recordatorio' => $inv->update(['recordatorio_at' => now()]),
            default => null,
        };
    }
}
