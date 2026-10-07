<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Envio extends Model
{
    public const MOTIVOS = [
        'invitacion' => 'Invitación',
        'recordatorio' => 'Recordatorio',
        'recordatorio_auto' => 'Recordatorio automático',
        'cambio' => 'Aviso de cambio',
        'posposicion' => 'Aviso de nueva fecha',
        'cancelacion' => 'Aviso de cancelación',
        'constancia' => 'Constancias',
    ];

    protected $fillable = ['evento_id', 'motivo', 'canal', 'asunto', 'mensaje', 'total', 'user_id'];

    protected $attributes = ['canal' => Invitacion::CORREO];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
