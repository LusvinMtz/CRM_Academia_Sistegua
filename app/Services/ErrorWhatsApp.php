<?php

namespace App\Services;

use RuntimeException;

class ErrorWhatsApp extends RuntimeException
{
    /** Segundos que pide esperar WasenderAPI cuando se alcanza el límite de envíos (0 si no aplica). */
    public function __construct(string $mensaje, public readonly int $esperar = 0)
    {
        parent::__construct($mensaje);
    }
}
