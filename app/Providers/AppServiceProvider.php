<?php

namespace App\Providers;

use App\Models\ConfiguracionCorreo;
use App\Models\User;
use App\Services\ProcesadorEnvios;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // El rol Administrador tiene todos los permisos, incluidos los que se agreguen después.
        Gate::before(fn (User $user) => $user->hasRole(User::ROL_ADMINISTRADOR) ? true : null);

        // Datos de envío guardados en Administración → Correo (reemplazan a los MAIL_* del .env).
        // Solo se consultan cuando la petición va a enviar un correo.
        $this->app->afterResolving('mail.manager', fn () => ConfiguracionCorreo::aplicarGuardada());

        // Envíos en cola (correo y WhatsApp): se procesan al terminar la petición web, sin tarea programada.
        Event::listen(JobQueued::class, fn () => ProcesadorEnvios::marcarNuevos());
        if (! $this->app->runningInConsole()) {
            $this->app->terminating(fn () => ProcesadorEnvios::alTerminarPeticion());
        }
    }
}
