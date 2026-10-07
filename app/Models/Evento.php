<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    public const CAPACITACION = 'capacitacion';

    public const PRESENCIAL = 'presencial';
    public const VIRTUAL = 'virtual';

    /**
     * Configuración de cada tipo: segmento de la URL, textos y a quién se dirige.
     */
    public const TIPOS = [
        'capacitaciones' => [
            'tipo' => self::CAPACITACION,
            'plural' => 'Capacitaciones',
            'singular' => 'capacitación',
            'nuevo' => 'Nueva capacitación',
            'icono' => 'ki-book-open',
            'publico' => Contacto::CLIENTE,
            'publico_texto' => 'clientes',
        ],
    ];

    protected $fillable = [
        'tipo', 'titulo', 'descripcion', 'sede_id', 'modalidad', 'inicio', 'fin',
        'lugar', 'enlace', 'facilitador', 'cupo', 'para_todos', 'recordatorio_automatico', 'creado_por',
        'plantilla_certificado_id',
    ];

    protected $attributes = ['para_todos' => false, 'recordatorio_automatico' => true];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'cancelado_at' => 'datetime',
            'recordatorio_enviado_at' => 'datetime',
            'cupo' => 'integer',
            'para_todos' => 'boolean',
            'recordatorio_automatico' => 'boolean',
        ];
    }

    /** Momento en que sale el recordatorio automático (p. ej. el día anterior a las 07:00). */
    public function momentoRecordatorio(): \Illuminate\Support\Carbon
    {
        $conf = config('academia.recordatorio');

        return $this->inicio->copy()->subDays($conf['dias_antes'])->setTimeFromTimeString($conf['hora']);
    }

    /**
     * Eventos cuyo recordatorio ya debería haber salido y todavía no sale.
     * Si el sistema estuvo apagado a la hora indicada, sale en cuanto vuelva a correr, siempre que el evento no haya empezado.
     */
    public static function conRecordatorioPendiente(): \Illuminate\Support\Collection
    {
        $dias = config('academia.recordatorio.dias_antes');

        return static::query()
            ->where('recordatorio_automatico', true)
            ->whereNull('recordatorio_enviado_at')
            ->whereNull('cancelado_at')
            ->where('inicio', '>', now())
            ->where('inicio', '<=', now()->addDays($dias + 1))
            ->orderBy('inicio')
            ->get()
            ->filter(fn (Evento $e) => $e->momentoRecordatorio()->lte(now()))
            ->values();
    }

    /**
     * Texto para la ficha del evento sobre el recordatorio. Por defecto se envía con el botón
     * "Enviar recordatorio"; el envío automático solo aplica si está activado en el servidor
     * (RECORDATORIO_AUTOMATICO=true y la tarea programada corriendo).
     */
    public function getEstadoRecordatorioAttribute(): string
    {
        $automatico = config('academia.recordatorio.automatico') && $this->recordatorio_automatico;

        return match (true) {
            $this->recordatorio_enviado_at !== null => 'Enviado el '.$this->recordatorio_enviado_at->translatedFormat('j \d\e F \a \l\a\s H:i').'.',
            $this->cancelado_at !== null || $this->inicio->isPast() => 'No se envió.',
            $automatico && $this->momentoRecordatorio()->isPast() => 'Pendiente: sale en la próxima revisión automática.',
            $automatico => 'Se enviará automáticamente el '.$this->momentoRecordatorio()->translatedFormat('l j \d\e F \a \l\a\s H:i').'.',
            $this->momentoRecordatorio()->isPast() => 'Aún no se ha enviado. Ya es buen momento: use el botón "Enviar recordatorio".',
            default => 'Aún no se ha enviado. Se recomienda enviarlo el '.$this->momentoRecordatorio()->translatedFormat('l j \d\e F').' con el botón "Enviar recordatorio".',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (Evento $e) {
            $e->token_registro ??= (string) \Illuminate\Support\Str::uuid();
        });
    }

    /** Minutos antes del inicio y después del fin en que el QR del evento acepta registros. */
    public const REGISTRO_ANTES = 60;
    public const REGISTRO_DESPUES = 30;

    public function getRegistroAbiertoAttribute(): bool
    {
        return $this->cancelado_at === null
            && now()->between($this->inicio->copy()->subMinutes(self::REGISTRO_ANTES), $this->fin->copy()->addMinutes(self::REGISTRO_DESPUES));
    }

    public function urlRegistro(): string
    {
        return route('registro.show', $this->token_registro);
    }

    /**
     * Control del evento: invitaciones enviadas vs confirmadas vs no confirmadas vs asistencias vs inasistencias,
     * y el cruce entre lo que respondieron y lo que realmente pasó.
     */
    public function control(): array
    {
        $todas = $this->invitaciones()->get(['estado_envio', 'respuesta', 'asistio', 'asistencia_metodo']);
        $enviadas = $todas->where('estado_envio', Invitacion::ENVIADA);
        $asistieron = $todas->whereStrict('asistio', true);
        $enviadasQueAsistieron = $enviadas->whereStrict('asistio', true);
        $confirmadas = $enviadas->where('respuesta', Invitacion::CONFIRMADA);
        $noConfirmadas = $enviadas->filter(fn ($i) => $i->respuesta !== Invitacion::CONFIRMADA);

        return [
            'enviadas' => $enviadas->count(),
            'confirmadas' => $confirmadas->count(),
            'no_confirmadas' => $noConfirmadas->count(),
            'rechazadas' => $enviadas->where('respuesta', Invitacion::RECHAZADA)->count(),
            'sin_respuesta' => $enviadas->whereNull('respuesta')->count(),
            'asistencias' => $asistieron->count(),
            // De los invitados por correo, quienes no llegaron (marcados ausentes o sin marcar)
            'inasistencias' => $enviadas->count() - $enviadasQueAsistieron->count(),
            'confirmaron_y_asistieron' => $confirmadas->whereStrict('asistio', true)->count(),
            'confirmaron_y_faltaron' => $confirmadas->count() - $confirmadas->whereStrict('asistio', true)->count(),
            'no_confirmaron_y_asistieron' => $noConfirmadas->whereStrict('asistio', true)->count(),
            'sin_invitacion_asistieron' => $asistieron->where('estado_envio', '!=', Invitacion::ENVIADA)->count(),
            'por_metodo' => collect(Invitacion::METODOS)->map(fn ($etq, $clave) => $asistieron->where('asistencia_metodo', $clave)->count())->all(),
            'asistencia_tomada' => $todas->whereNotNull('asistio')->isNotEmpty(),
        ];
    }

    public static function segmentoDe(string $tipo): string
    {
        return 'capacitaciones';
    }

    public function config(): array
    {
        return self::TIPOS[self::segmentoDe($this->tipo)];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class)->orderBy('nombre');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Diseño de certificado propio; si es null se usa el predeterminado. */
    public function plantillaCertificado(): BelongsTo
    {
        return $this->belongsTo(PlantillaCertificado::class, 'plantilla_certificado_id');
    }

    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class);
    }

    public function envios(): HasMany
    {
        return $this->hasMany(Envio::class)->latest();
    }

    /**
     * Si alguien ya recibió (o está por recibir) la invitación, el evento no se puede eliminar:
     * solo anular o posponer, y siempre avisando por correo.
     */
    public function tieneInvitacionesEnviadas(): bool
    {
        return $this->invitaciones()->whereIn('estado_envio', [Invitacion::ENVIADA, Invitacion::PENDIENTE])->exists();
    }

    /** Se aceptan respuestas mientras el evento no haya terminado ni esté cancelado. */
    public function getAceptaRespuestasAttribute(): bool
    {
        return $this->cancelado_at === null && $this->fin->isFuture();
    }

    public function getCupoLlenoAttribute(): bool
    {
        return $this->cupo !== null && $this->invitaciones()->confirmadas()->count() >= $this->cupo;
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeProximos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_at')->where('fin', '>=', now());
    }

    public function scopeEstado(Builder $query, ?string $estado): Builder
    {
        return match ($estado) {
            'proximos' => $query->whereNull('cancelado_at')->where('fin', '>=', now()),
            'realizados' => $query->whereNull('cancelado_at')->where('fin', '<', now()),
            'cancelados' => $query->whereNotNull('cancelado_at'),
            default => $query,
        };
    }

    public function getEstadoAttribute(): string
    {
        return match (true) {
            $this->cancelado_at !== null => 'cancelado',
            $this->fin->isPast() => 'realizado',
            $this->inicio->isPast() => 'en_curso',
            default => 'programado',
        };
    }

    public function getEstadoEtiquetaAttribute(): array
    {
        return [
            'cancelado' => ['Anulado', 'danger'],
            'realizado' => ['Realizado', 'dark'],
            'en_curso' => ['En curso', 'success'],
            'programado' => ['Programado', 'primary'],
        ][$this->estado];
    }

    public function getEsVirtualAttribute(): bool
    {
        return $this->modalidad === self::VIRTUAL;
    }

    /** Nombre de la plataforma según el enlace (para mostrarlo en la invitación). */
    public function getPlataformaAttribute(): ?string
    {
        if (! $this->enlace) {
            return null;
        }
        $host = strtolower((string) parse_url($this->enlace, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'zoom') => 'Zoom',
            str_contains($host, 'meet.google') => 'Google Meet',
            str_contains($host, 'teams') => 'Microsoft Teams',
            default => 'Enlace virtual',
        };
    }

    /** "Martes 14 de octubre de 2026, de 15:00 a 17:00 h" */
    public function getHorarioAttribute(): string
    {
        $fecha = ucfirst($this->inicio->translatedFormat('l j \d\e F \d\e Y'));

        return "{$fecha}, de {$this->inicio->format('H:i')} a {$this->fin->format('H:i')} h";
    }

    public function getDuracionHorasAttribute(): float
    {
        return round($this->inicio->diffInMinutes($this->fin) / 60, 2);
    }

    /** "2 horas", "1 hora", "4,5 horas" */
    public function getDuracionTextoAttribute(): string
    {
        $h = $this->duracion_horas;
        $numero = rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ',');

        return $numero.' '.($h == 1 ? 'hora' : 'horas');
    }

    /**
     * Personas activas a las que va dirigido, sin repetir: todos los del público de la sede,
     * o los miembros de los grupos elegidos que pertenecen a la sede del evento.
     */
    public function destinatarios(): Builder
    {
        $query = Contacto::query()->activos()->where('sede_id', $this->sede_id);

        if ($this->para_todos) {
            return $query->where('tipo', $this->config()['publico']);
        }

        $grupos = $this->grupos()->pluck('grupos.id');

        return $query->whereHas('grupos', fn ($q) => $q->whereIn('grupos.id', $grupos));
    }

    /** Destinatarios que sí pueden recibir la invitación por correo. */
    public function destinatariosConCorreo(): Builder
    {
        return $this->destinatarios()->whereNotNull('correo')->where('acepta_correos', true);
    }

    /** Quienes pidieron no recibir correos tampoco reciben WhatsApp. */
    public function destinatariosConTelefono(): Builder
    {
        return $this->destinatarios()->whereNotNull('telefono')->where('telefono', '!=', '')->where('acepta_correos', true);
    }

    /** Destinatarios a los que se puede escribir por el canal indicado (Invitacion::CORREO o ::WHATSAPP). */
    public function destinatariosPor(string $canal): Builder
    {
        return $canal === Invitacion::WHATSAPP ? $this->destinatariosConTelefono() : $this->destinatariosConCorreo();
    }
}
