<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * Cuenta de WasenderAPI desde la que salen los mensajes de WhatsApp (un solo registro).
 */
class ConfiguracionWhatsapp extends Model
{
    public const API = 'api';
    public const PRUEBA = 'log';

    public const MODOS = [
        self::API => 'Envío real (WasenderAPI)',
        self::PRUEBA => 'Modo de prueba (se guardan en storage/logs/whatsapp.log sin enviarse)',
    ];

    protected $table = 'configuracion_whatsapp';

    /** Con la protección activa, nunca menos de esto entre dos mensajes (lo que exige la protección de cuenta de WasenderAPI). */
    public const PAUSA_MINIMA_PROTECCION = 5;

    protected $fillable = ['modo', 'token', 'codigo_pais', 'pausa_segundos', 'proteccion', 'actualizado_por'];

    protected $hidden = ['token'];

    protected $attributes = ['modo' => self::PRUEBA, 'codigo_pais' => '502', 'pausa_segundos' => 8, 'proteccion' => true];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'pausa_segundos' => 'integer',
            'proteccion' => 'boolean',
        ];
    }

    /**
     * Segundos hasta el siguiente mensaje. Con la protección, al menos 5 y con una variación al azar
     * (entre la pausa y el doble), para que los envíos no tengan un ritmo de robot.
     */
    public function espacioEntreMensajes(): int
    {
        if (! $this->proteccion) {
            return $this->pausa_segundos;
        }
        $pausa = max($this->pausa_segundos, self::PAUSA_MINIMA_PROTECCION);

        return random_int($pausa, $pausa * 2);
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** El registro guardado o uno sin guardar con los valores por defecto. */
    public static function actual(): self
    {
        try {
            return static::query()->first() ?? new static();
        } catch (Throwable) {
            return new static(); // Sin migrar todavía
        }
    }

    public function enModoPrueba(): bool
    {
        return $this->modo !== self::API;
    }

    public function tieneToken(): bool
    {
        return filled($this->token);
    }
}
