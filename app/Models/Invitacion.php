<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitacion extends Model
{
    protected $table = 'invitaciones';

    public const PENDIENTE = 'pendiente';
    public const ENVIADA = 'enviada';
    public const FALLIDA = 'fallida';
    /** Registro solo para asistencia: la persona no tiene correo o llegó sin invitación. */
    public const NO_ENVIADA = 'no_enviada';

    public const CONFIRMADA = 'confirmada';
    public const RECHAZADA = 'rechazada';

    /** Canales de envío y su etiqueta. */
    public const CORREO = 'correo';
    public const WHATSAPP = 'whatsapp';

    public const CANALES = [self::CORREO => 'Correo', self::WHATSAPP => 'WhatsApp'];

    protected $fillable = [
        'evento_id', 'contacto_id', 'correo', 'telefono', 'estado_envio', 'correo_estado', 'whatsapp_estado', 'error',
        'respuesta', 'comentario', 'respuesta_por', 'enviada_at', 'whatsapp_at', 'vista_at', 'respondida_at', 'recordatorio_at',
        'asistio', 'asistencia_at', 'asistencia_por', 'asistencia_metodo',
    ];

    /**
     * Deja la invitación lista para enviarse por un canal (la crea si no existía) y la guarda.
     * Toma el correo o teléfono actual del contacto.
     */
    public static function prepararCanal(Evento $evento, Contacto $contacto, string $canal): self
    {
        $inv = static::firstOrNew(['evento_id' => $evento->id, 'contacto_id' => $contacto->id]);
        $inv->setRelation('contacto', $contacto);
        $inv->fill($canal === self::CORREO ? ['correo' => $contacto->correo] : ['telefono' => $contacto->telefono]);
        $inv->marcarCanal($canal, self::PENDIENTE);

        return $inv;
    }

    /**
     * Registra el resultado de un canal y recalcula el estado general: enviada si algún canal llegó,
     * si no pendiente si alguno está en camino, si no fallida.
     */
    public function marcarCanal(string $canal, string $estado, ?string $error = null): void
    {
        $this->{$canal.'_estado'} = $estado;

        if ($estado === self::ENVIADA) {
            $this->error = null;
            $this->enviada_at ??= now();
            if ($canal === self::WHATSAPP) {
                $this->whatsapp_at = now();
            }
        } elseif ($estado === self::FALLIDA) {
            $this->error = self::CANALES[$canal].': '.$error;
        }

        $estados = [$this->correo_estado, $this->whatsapp_estado];
        $this->estado_envio = match (true) {
            in_array(self::ENVIADA, $estados, true) => self::ENVIADA,
            in_array(self::PENDIENTE, $estados, true) => self::PENDIENTE,
            in_array(self::FALLIDA, $estados, true) => self::FALLIDA,
            default => $this->estado_envio,
        };
        $this->save();
    }

    /** Canales por los que ya le llegó la invitación. */
    public function canalesEnviados(): array
    {
        // Sin estado por canal: registro anterior a WhatsApp, enviado por correo
        $correo = $this->correo_estado ?? ($this->whatsapp_estado ? null : $this->estado_envio);

        return array_keys(array_filter([
            self::CORREO => $correo === self::ENVIADA && $this->correo,
            self::WHATSAPP => $this->whatsapp_estado === self::ENVIADA && $this->telefono,
        ]));
    }

    /** Cómo se registró la asistencia. */
    public const METODOS = [
        'manual' => 'Lista de la academia',
        'qr_evento' => 'QR del evento',
        'qr_personal' => 'QR personal',
    ];

    /**
     * Marca presente o ausente a una persona en un evento. Si no tenía invitación (sin correo o llegó sin invitación)
     * se crea un registro "no enviada" solo para la asistencia.
     */
    public static function registrarAsistencia(Evento $evento, Contacto $contacto, bool $presente, ?int $usuarioId, string $metodo): self
    {
        $inv = static::firstOrNew(['evento_id' => $evento->id, 'contacto_id' => $contacto->id]);
        if (! $inv->exists) {
            $inv->fill(['estado_envio' => self::NO_ENVIADA, 'correo' => $contacto->correo]);
        }
        if ($inv->asistio !== $presente) {
            $inv->fill(['asistio' => $presente, 'asistencia_at' => now(), 'asistencia_por' => $usuarioId, 'asistencia_metodo' => $metodo]);
        }
        $inv->save();

        return $inv;
    }

    /** Contenido del QR personal: el personal de la academia lo escanea en la entrada. */
    public function urlEscaneo(): string
    {
        return route('escaneo.show', $this->token);
    }

    protected $attributes = ['estado_envio' => self::PENDIENTE];

    protected function casts(): array
    {
        return [
            'enviada_at' => 'datetime',
            'vista_at' => 'datetime',
            'respondida_at' => 'datetime',
            'recordatorio_at' => 'datetime',
            'asistio' => 'boolean',
            'asistencia_at' => 'datetime',
        ];
    }

    public function scopeAsistentes(Builder $q): Builder
    {
        return $q->where('asistio', true);
    }

    /** Código corto y único para verificar la constancia (se genera la primera vez). */
    public function codigoConstancia(): string
    {
        if (! $this->codigo_constancia) {
            do {
                $codigo = strtoupper(Str::random(4).'-'.Str::random(4));
                $codigo = strtr($codigo, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'K']);
            } while (static::where('codigo_constancia', $codigo)->exists());
            $this->forceFill(['codigo_constancia' => $codigo])->save();
        }

        return $this->codigo_constancia;
    }

    protected static function booted(): void
    {
        static::creating(function (Invitacion $i) {
            $i->token ??= (string) Str::uuid();
        });
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function scopeSinRespuesta(Builder $q): Builder
    {
        return $q->whereNull('respuesta');
    }

    public function scopeConfirmadas(Builder $q): Builder
    {
        return $q->where('respuesta', self::CONFIRMADA);
    }

    /** Filtro de la lista de seguimiento. */
    public function scopeSituacion(Builder $q, ?string $situacion): Builder
    {
        return match ($situacion) {
            'confirmadas' => $q->where('respuesta', self::CONFIRMADA),
            'rechazadas' => $q->where('respuesta', self::RECHAZADA),
            'sin_respuesta' => $q->whereNull('respuesta')->where('estado_envio', self::ENVIADA),
            'no_vistas' => $q->whereNull('vista_at')->whereNull('respuesta')->where('estado_envio', self::ENVIADA),
            'fallidas' => $q->where('estado_envio', self::FALLIDA),
            'pendientes' => $q->where('estado_envio', self::PENDIENTE),
            default => $q,
        };
    }

    public function getSituacionAttribute(): array
    {
        return match (true) {
            $this->respuesta === self::CONFIRMADA => ['Confirmó', 'success', 'ki-check-circle'],
            $this->respuesta === self::RECHAZADA => ['No asistirá', 'danger', 'ki-cross-circle'],
            $this->estado_envio === self::FALLIDA => ['Error de envío', 'danger', 'ki-information'],
            $this->estado_envio === self::PENDIENTE => ['Pendiente de envío', 'warning', 'ki-time'],
            $this->vista_at !== null => ['Vio la invitación', 'info', 'ki-eye'],
            default => ['Enviada, sin respuesta', 'dark', 'ki-sms'],
        };
    }

    public function url(?string $respuesta = null): string
    {
        return route('invitacion.show', ['token' => $this->token] + ($respuesta ? ['r' => $respuesta] : []));
    }
}
