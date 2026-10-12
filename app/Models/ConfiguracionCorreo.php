<?php

namespace App\Models;

use App\Casts\CifradoTolerante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class ConfiguracionCorreo extends Model
{
    public const SMTP = 'smtp';
    public const PRUEBA = 'log';

    public const MODOS = [
        self::SMTP => 'Envío real (SMTP)',
        self::PRUEBA => 'Modo de prueba (se guardan en storage/logs/correos.log sin enviarse)',
    ];

    public const CIFRADOS = [
        'tls' => 'TLS / STARTTLS (puerto 587)',
        'ssl' => 'SSL (puerto 465)',
    ];

    /** Servidores comunes para llenar el formulario con un clic. */
    public const PROVEEDORES = [
        'Gmail' => ['host' => 'smtp.gmail.com', 'puerto' => 587, 'cifrado' => 'tls'],
        'Outlook / Office 365' => ['host' => 'smtp.office365.com', 'puerto' => 587, 'cifrado' => 'tls'],
        'Brevo' => ['host' => 'smtp-relay.brevo.com', 'puerto' => 587, 'cifrado' => 'tls'],
        'Mailgun' => ['host' => 'smtp.mailgun.org', 'puerto' => 587, 'cifrado' => 'tls'],
        'Amazon SES (us-east-1)' => ['host' => 'email-smtp.us-east-1.amazonaws.com', 'puerto' => 587, 'cifrado' => 'tls'],
    ];

    protected $table = 'configuracion_correo';

    protected $fillable = [
        'modo', 'host', 'puerto', 'cifrado', 'usuario', 'password',
        'remitente_correo', 'remitente_nombre', 'actualizado_por',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'puerto' => 'integer',
            'password' => CifradoTolerante::class,
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** El registro guardado o, si todavía no hay, uno sin guardar con los valores del .env. */
    public static function actual(): self
    {
        return static::query()->first() ?? new static([
            'modo' => config('mail.default') === self::SMTP ? self::SMTP : self::PRUEBA,
            'host' => config('mail.mailers.smtp.host'),
            'puerto' => config('mail.mailers.smtp.port'),
            'cifrado' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'usuario' => config('mail.mailers.smtp.username'),
            'remitente_correo' => config('mail.from.address'),
            'remitente_nombre' => config('mail.from.name'),
        ]);
    }

    public function tienePassword(): bool
    {
        return filled($this->password) || (! $this->exists && filled(config('mail.mailers.smtp.password')));
    }

    /**
     * Pone esta configuración en config('mail') para los correos siguientes.
     * Se llama al crear el administrador de correo (AppServiceProvider) y después de guardar.
     */
    public function aplicar(): void
    {
        config([
            'mail.default' => $this->modo,
            'mail.from.address' => $this->remitente_correo,
            'mail.from.name' => $this->remitente_nombre,
        ]);

        if ($this->modo === self::SMTP) {
            config([
                'mail.mailers.smtp.host' => $this->host,
                'mail.mailers.smtp.port' => $this->puerto,
                'mail.mailers.smtp.scheme' => $this->cifrado === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $this->usuario,
                'mail.mailers.smtp.password' => $this->password,
            ]);
        }

        // Olvida los mailers ya creados para que tomen los datos nuevos
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->purge(self::SMTP);
            app('mail.manager')->purge(self::PRUEBA);
        }
    }

    /** Aplica la configuración guardada, si la hay. No falla si la tabla aún no existe (antes de migrar). */
    public static function aplicarGuardada(): void
    {
        try {
            static::query()->first()?->aplicar();
        } catch (Throwable) {
            // Sin base de datos o sin migrar: se quedan los valores del .env
        }
    }
}
