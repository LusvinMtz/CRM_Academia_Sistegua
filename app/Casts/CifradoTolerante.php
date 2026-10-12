<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Igual que el cast "encrypted", pero si el valor se cifró con otra APP_KEY (p. ej. la base se
 * comparte con otro servidor o se cambió la clave) lo trata como vacío en lugar de lanzar un
 * error 500: la pantalla de configuración abre y basta con volver a escribir el secreto.
 */
class CifradoTolerante implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString($value);
    }
}
