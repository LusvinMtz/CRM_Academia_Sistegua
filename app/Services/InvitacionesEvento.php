<?php

namespace App\Services;

use App\Jobs\EnviarCorreoEvento;
use App\Jobs\EnviarWhatsAppEvento;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Plantilla;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Decide a quién se escribe en cada caso y despacha los correos y mensajes de WhatsApp de un evento.
 */
class InvitacionesEvento
{
    /** Textos por defecto de los correos que no son la invitación inicial. */
    public const TEXTOS = [
        'recordatorio' => [
            'asunto' => 'Recordatorio: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe recordamos la actividad \"{titulo}\" del {fecha}, de {hora}, en {lugar}.\n\nTodavía no hemos recibido su respuesta. Le agradecemos confirmar si podrá asistir.",
        ],
        'cambio' => [
            'asunto' => 'Cambio en: {titulo}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe informamos que la actividad \"{titulo}\" tuvo cambios. Los datos actualizados son: {fecha}, de {hora}, en {lugar}.\n\nSi su disponibilidad cambió, puede actualizar su respuesta con los botones de este correo.",
        ],
        'posposicion' => [
            'asunto' => 'Nueva fecha: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe informamos que la actividad \"{titulo}\" fue pospuesta. La nueva fecha es el {fecha}, de {hora}, en {lugar}.\n\nSi con la nueva fecha su disponibilidad cambió, por favor actualice su respuesta con los botones de este correo.",
        ],
        'cancelacion' => [
            'asunto' => 'Cancelada: {titulo}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe informamos que la actividad \"{titulo}\" programada para el {fecha} fue cancelada.\n\nDisculpe los inconvenientes.",
        ],
    ];

    /** Recordatorio automático del día anterior: un texto para quien confirmó y otro para quien no ha respondido. */
    public const TEXTOS_AUTOMATICOS = [
        'confirmados' => [
            'asunto' => 'Le esperamos: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nGracias por confirmar su asistencia. Le recordamos que le esperamos el {fecha}, de {hora}, en {lugar}.\n\nSi a última hora no puede asistir, por favor avísenos con el botón de este correo.",
        ],
        'sin_respuesta' => [
            'asunto' => 'Recordatorio: {titulo} — {fecha}',
            'mensaje' => "Estimado(a) {nombre}:\n\nLe recordamos la actividad \"{titulo}\" del {fecha}, de {hora}, en {lugar}.\n\nAún no hemos recibido su confirmación. Le agradecemos indicarnos si podrá asistir.",
        ],
    ];

    public function __construct(private readonly Evento $evento)
    {
    }

    public static function textoPorDefecto(string $motivo, Evento $evento): array
    {
        if ($motivo === 'invitacion') {
            $p = Plantilla::predeterminadaPara($evento->tipo);

            return ['asunto' => $p?->asunto ?? 'Invitación: {titulo}', 'mensaje' => $p?->mensaje ?? "Estimado(a) {nombre}:\n\nLe invitamos a \"{titulo}\"."];
        }

        return self::TEXTOS[$motivo] ?? self::TEXTOS_AUTOMATICOS['sin_respuesta'];
    }

    /** Destinatarios con correo (o teléfono) que todavía no recibieron la invitación por ese canal. */
    public function porInvitar(string $canal = Invitacion::CORREO): Builder
    {
        return $this->evento->destinatariosPor($canal)
            ->whereDoesntHave('invitaciones', fn ($q) => $q->where('evento_id', $this->evento->id)->whereNotNull($canal.'_estado'));
    }

    /** Invitaciones enviadas que aún no tienen respuesta. */
    public function porRecordar(string $canal = Invitacion::CORREO): Builder
    {
        return $this->recordables($canal)->whereNull('respuesta');
    }

    /** Invitaciones enviadas de quienes confirmaron. */
    public function confirmadosPorRecordar(string $canal = Invitacion::CORREO): Builder
    {
        return $this->recordables($canal)->where('respuesta', Invitacion::CONFIRMADA);
    }

    /** Invitadas (por cualquier canal) a las que se puede escribir por $canal. */
    private function recordables(string $canal): Builder
    {
        $campo = $canal === Invitacion::WHATSAPP ? 'telefono' : 'correo';

        return $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->whereHas('contacto', fn ($q) => $q->activos()->where('acepta_correos', true)->whereNotNull($campo)->where($campo, '!=', ''));
    }

    /** Invitaciones a quienes avisar de un cambio o una cancelación (no a quienes dijeron que no irán). */
    public function porAvisar(): Builder
    {
        return $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->where(fn ($q) => $q->whereNull('respuesta')->orWhere('respuesta', Invitacion::CONFIRMADA))
            ->whereHas('contacto', fn ($q) => $q->activos()->where('acepta_correos', true));
    }

    public function invitar(string $asunto, string $mensaje, ?User $usuario, string $canal = Invitacion::CORREO): int
    {
        $contactos = $this->porInvitar($canal)->get();
        if ($contactos->isEmpty()) {
            return 0;
        }

        // Si ya tenía registro (invitado por el otro canal o en la lista de asistencia), se reutiliza
        $invitaciones = DB::transaction(fn () => $contactos->map(
            fn (Contacto $c) => Invitacion::prepararCanal($this->evento, $c, $canal)
        ));

        $this->registrarEnvio('invitacion', $canal, $asunto, $mensaje, $invitaciones->count(), $usuario);
        $invitaciones->each(fn (Invitacion $i) => $this->despachar($i, $canal, 'invitacion', $asunto, $mensaje));

        return $invitaciones->count();
    }

    /**
     * Recordatorio desde el botón de la ficha del evento. Cada grupo lleva su propio texto:
     * $textos = ['sin_respuesta' => ['asunto' => …, 'mensaje' => …], 'confirmados' => [...]].
     * Solo se escribe a los grupos incluidos en $textos. Devuelve [confirmados, sin respuesta].
     */
    public function recordar(array $textos, ?User $usuario, string $canal = Invitacion::CORREO): array
    {
        $grupos = [
            'confirmados' => isset($textos['confirmados']) ? $this->confirmadosPorRecordar($canal)->get() : collect(),
            'sin_respuesta' => isset($textos['sin_respuesta']) ? $this->porRecordar($canal)->get() : collect(),
        ];

        foreach ($grupos as $clave => $invitaciones) {
            if ($invitaciones->isNotEmpty()) {
                $this->enviarA($invitaciones, $canal, 'recordatorio', $textos[$clave]['asunto'], $textos[$clave]['mensaje'], $usuario);
            }
        }

        if ($grupos['confirmados']->isNotEmpty() || $grupos['sin_respuesta']->isNotEmpty()) {
            $this->evento->forceFill(['recordatorio_enviado_at' => now()])->save();
        }

        return [$grupos['confirmados']->count(), $grupos['sin_respuesta']->count()];
    }

    /**
     * Aviso de cambio, posposición o cancelación, por los mismos canales por los que se invitó a cada persona.
     * $nota se agrega al final del mensaje (por ejemplo, la fecha anterior y el motivo de una posposición).
     */
    public function avisar(string $motivo, ?User $usuario, ?string $asunto = null, ?string $mensaje = null, ?string $nota = null): int
    {
        $texto = self::TEXTOS[$motivo];
        $mensaje ??= $texto['mensaje'];
        if ($nota) {
            $mensaje .= "\n\n{$nota}";
        }
        if ($motivo === 'cancelacion' && $this->evento->motivo_cancelacion) {
            $mensaje .= "\n\nMotivo: {$this->evento->motivo_cancelacion}";
        }

        $invitaciones = $this->porAvisar()->get();
        $this->enviarPorSusCanales($invitaciones, $motivo, $motivo, $asunto ?? $texto['asunto'], $mensaje, $usuario);

        return $invitaciones->count();
    }

    /**
     * Recordatorio automático (lo lanza la tarea programada). No se envía a quienes dijeron que no,
     * ni a quienes fueron invitados después del momento del recordatorio (ya recibieron su invitación reciente).
     * Va por los mismos canales por los que se invitó a cada persona.
     * Devuelve [confirmados, sin respuesta] a quienes se escribió.
     */
    public function recordatorioAutomatico(): array
    {
        $momento = $this->evento->momentoRecordatorio();
        $base = fn () => $this->evento->invitaciones()->getQuery()
            ->where('estado_envio', Invitacion::ENVIADA)
            ->where('enviada_at', '<', $momento)
            ->whereHas('contacto', fn ($q) => $q->activos()->where('acepta_correos', true));

        $grupos = [
            'confirmados' => $base()->where('respuesta', Invitacion::CONFIRMADA)->get(),
            'sin_respuesta' => $base()->whereNull('respuesta')->get(),
        ];

        foreach ($grupos as $clave => $invitaciones) {
            $texto = self::TEXTOS_AUTOMATICOS[$clave];
            $this->enviarPorSusCanales($invitaciones, 'recordatorio', 'recordatorio_auto', $texto['asunto'], $texto['mensaje'], null);
        }

        $this->evento->forceFill(['recordatorio_enviado_at' => now()])->save();

        return [$grupos['confirmados']->count(), $grupos['sin_respuesta']->count()];
    }

    /** Vuelve a enviar la invitación a una persona (p. ej. después de corregir su correo o su teléfono). */
    public function reenviar(Invitacion $invitacion, ?User $usuario, string $canal = Invitacion::CORREO): void
    {
        $invitacion = Invitacion::prepararCanal($this->evento, $invitacion->contacto, $canal);
        $texto = self::textoPorDefecto('invitacion', $this->evento);
        $this->registrarEnvio('invitacion', $canal, $texto['asunto'], $texto['mensaje'], 1, $usuario);
        $this->despachar($invitacion, $canal, 'invitacion', $texto['asunto'], $texto['mensaje']);
    }

    private function enviarA($invitaciones, string $canal, string $motivo, string $asunto, string $mensaje, ?User $usuario): int
    {
        if ($invitaciones->isEmpty()) {
            return 0;
        }
        $this->registrarEnvio($motivo, $canal, $asunto, $mensaje, $invitaciones->count(), $usuario);
        $invitaciones->each(fn (Invitacion $i) => $this->despachar($i, $canal, $motivo, $asunto, $mensaje));

        return $invitaciones->count();
    }

    /** Escribe a cada invitación por los canales por los que le llegó la invitación ($motivoEnvio es el del historial). */
    private function enviarPorSusCanales($invitaciones, string $motivo, string $motivoEnvio, string $asunto, string $mensaje, ?User $usuario): void
    {
        $invitaciones->load('contacto');
        foreach (array_keys(Invitacion::CANALES) as $canal) {
            $grupo = $invitaciones->filter(fn (Invitacion $i) => in_array($canal, $i->canalesEnviados(), true));
            if ($grupo->isNotEmpty()) {
                $this->registrarEnvio($motivoEnvio, $canal, $asunto, $mensaje, $grupo->count(), $usuario);
                $grupo->each(fn (Invitacion $i) => $this->despachar($i, $canal, $motivo, $asunto, $mensaje));
            }
        }
    }

    private function despachar(Invitacion $inv, string $canal, string $motivo, string $asunto, string $mensaje): void
    {
        $inv->setRelation('evento', $this->evento);
        $contacto = $inv->contacto;
        $mensaje = Plantilla::rellenar($mensaje, $this->evento, $contacto);

        if ($canal === Invitacion::WHATSAPP) {
            // Cada mensaje en su turno, espaciado según la protección configurada
            EnviarWhatsAppEvento::dispatch($inv, $motivo, $mensaje)->delay(WhatsApp::turno());

            return;
        }

        if (! $inv->correo && $contacto->correo) {
            $inv->update(['correo' => $contacto->correo]); // Invitado antes solo por WhatsApp
        }
        EnviarCorreoEvento::dispatch($inv, $motivo, Plantilla::rellenar($asunto, $this->evento, $contacto), $mensaje);
    }

    private function registrarEnvio(string $motivo, string $canal, string $asunto, string $mensaje, int $total, ?User $usuario): void
    {
        Envio::create([
            'evento_id' => $this->evento->id, 'motivo' => $motivo, 'canal' => $canal, 'asunto' => $asunto,
            'mensaje' => $mensaje, 'total' => $total, 'user_id' => $usuario?->id,
        ]);
    }
}
