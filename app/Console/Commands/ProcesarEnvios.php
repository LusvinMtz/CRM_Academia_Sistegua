<?php

namespace App\Console\Commands;

use App\Services\ProcesadorEnvios;
use Illuminate\Console\Command;

/**
 * Envía los correos y mensajes de WhatsApp pendientes y termina cuando la cola queda vacía.
 * Normalmente lo lanza el propio sistema (ver ProcesadorEnvios); también se puede ejecutar a mano.
 */
class ProcesarEnvios extends Command
{
    protected $signature = 'envios:procesar';

    protected $description = 'Envía los correos y mensajes de WhatsApp pendientes, respetando la pausa entre mensajes';

    public function handle(): int
    {
        $total = (new ProcesadorEnvios())->procesar();
        $this->info("Envíos procesados: {$total}.");

        return self::SUCCESS;
    }
}
