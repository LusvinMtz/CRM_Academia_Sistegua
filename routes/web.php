<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ConfiguracionCorreoController;
use App\Http\Controllers\ConfiguracionWhatsappController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConstanciaController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GeografiaController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\InvitacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PlantillaCertificadoController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\RegistroQrController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RespuestaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// Páginas públicas del invitado (enlaces del correo, sin iniciar sesión)
Route::prefix('invitacion/{token}')->whereUuid('token')->name('invitacion.')->middleware('throttle:30,1')
    ->controller(RespuestaController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'responder')->name('responder');
        Route::get('/calendario.ics', 'calendario')->name('calendario');
        Route::get('/baja', 'bajaForm')->name('baja');
        Route::post('/baja', 'baja')->name('baja.store');
    });

// QR del evento: los asistentes se registran desde su celular con su correo o DPI
Route::prefix('registro/{token}')->whereUuid('token')->name('registro.')->middleware('throttle:20,1')
    ->controller(RegistroQrController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'registrar')->name('registrar');
    });

// Verificación pública de constancias (el código viene impreso en el PDF)
Route::get('/constancia/verificar/{codigo?}', [ConstanciaController::class, 'verificar'])
    ->middleware('throttle:30,1')->name('constancia.verificar');
// PDF de la constancia por enlace firmado (WasenderAPI lo descarga para enviarlo por WhatsApp)
Route::get('/constancia/{token}/pdf', [ConstanciaController::class, 'publica'])->whereUuid('token')
    ->middleware(['signed', 'throttle:30,1'])->name('constancia.publica');

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'password'])->name('perfil.password');

    // Academia
    Route::resource('sedes', SedeController::class)->except(['show', 'destroy']);
    Route::patch('/sedes/{sede}/estado', [SedeController::class, 'estado'])->name('sedes.estado');

    // Clientes (/contactos/clientes)
    Route::prefix('contactos/{tipo}')->whereIn('tipo', array_keys(\App\Models\Contacto::TIPOS))
        ->name('contactos.')->controller(ContactoController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/nuevo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/importar', 'importarForm')->name('importar');
            Route::post('/importar', 'importar')->name('importar.store');
            Route::get('/plantilla', 'plantilla')->name('plantilla');
            Route::get('/exportar', 'exportar')->name('exportar');
            Route::post('/masivo', 'masivo')->name('masivo');
            Route::get('/{contacto}/editar', 'edit')->name('edit');
            Route::put('/{contacto}', 'update')->name('update');
            Route::patch('/{contacto}/estado', 'estado')->name('estado');
        });

    // Capacitaciones (/eventos/capacitaciones)
    Route::prefix('eventos/{tipo}')->whereIn('tipo', array_keys(\App\Models\Evento::TIPOS))
        ->name('eventos.')->controller(EventoController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/nuevo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{evento}', 'show')->name('show');
            Route::get('/{evento}/editar', 'edit')->name('edit');
            Route::put('/{evento}', 'update')->name('update');
            Route::delete('/{evento}', 'destroy')->name('destroy');
            Route::patch('/{evento}/cancelar', 'cancelar')->name('cancelar');
            Route::patch('/{evento}/posponer', 'posponer')->name('posponer');
            Route::patch('/{evento}/reactivar', 'reactivar')->name('reactivar');
            Route::get('/{evento}/duplicar', 'duplicar')->name('duplicar');
            Route::get('/{evento}/calendario.ics', 'calendario')->name('calendario');
        });

    // Invitaciones de un evento
    Route::prefix('eventos/{tipo}/{evento}/invitaciones')->whereIn('tipo', array_keys(\App\Models\Evento::TIPOS))
        ->name('invitaciones.')->controller(InvitacionController::class)->group(function () {
            Route::post('/enviar', 'enviar')->name('enviar');
            Route::post('/recordar', 'recordar')->name('recordar');
            Route::get('/vista-previa', 'vistaPrevia')->name('vista-previa');
            Route::post('/{invitacion}/reenviar', 'reenviar')->name('reenviar');
            Route::patch('/{invitacion}/respuesta', 'responder')->name('responder');
        });
    // Asistencia de un evento
    Route::prefix('eventos/{tipo}/{evento}/asistencia')->whereIn('tipo', array_keys(\App\Models\Evento::TIPOS))
        ->name('asistencia.')->controller(AsistenciaController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'guardar')->name('guardar');
            Route::post('/agregar', 'agregar')->name('agregar');
            Route::get('/buscar', 'buscar')->name('buscar');
            Route::get('/lista.pdf', 'pdf')->name('pdf');
            Route::get('/asistencia.xlsx', 'excel')->name('excel');
        });
    Route::prefix('eventos/{tipo}/{evento}/qr')->whereIn('tipo', array_keys(\App\Models\Evento::TIPOS))
        ->name('asistencia.qr')->controller(RegistroQrController::class)->group(function () {
            Route::get('/', 'cartel');
            Route::get('/qr.png', 'descargarPng')->name('.png');
            Route::post('/renovar', 'renovar')->name('.renovar');
        });

    // QR personal: el personal de la academia lo escanea con la cámara del celular en la entrada
    Route::get('/escaneo/{token}', [RegistroQrController::class, 'escanear'])->whereUuid('token')->name('escaneo.show');

    // Constancias de capacitaciones
    Route::prefix('capacitaciones/{evento}/constancias')->name('constancias.')->controller(ConstanciaController::class)->group(function () {
        Route::get('/', 'todas')->name('todas');
        Route::post('/enviar', 'enviar')->name('enviar');
        Route::get('/{invitacion}', 'descargar')->name('descargar');
    });

    // Diseños de certificado (editor visual)
    Route::prefix('certificados')->name('certificados.')->controller(PlantillaCertificadoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/nuevo', 'create')->name('create');
        Route::post('/imagenes', 'imagen')->name('imagen');
        Route::get('/imagenes/{archivo}', 'verImagen')->name('imagen.ver');
        Route::get('/{certificado}/editar', 'edit')->name('edit');
        Route::put('/{certificado}', 'update')->name('update');
        Route::get('/{certificado}/vista-previa', 'vistaPrevia')->name('vista-previa');
        Route::post('/{certificado}/duplicar', 'duplicar')->name('duplicar');
        Route::patch('/{certificado}/predeterminada', 'predeterminada')->name('predeterminada');
        Route::delete('/{certificado}', 'destroy')->name('destroy');
    });

    // Reportes
    Route::prefix('reportes')->name('reportes.')->controller(ReporteController::class)->group(function () {
        Route::get('/eventos', 'eventos')->name('eventos');
        Route::get('/eventos.xlsx', 'eventosExcel')->name('eventos.excel');
        Route::get('/personas', 'personas')->name('personas');
        Route::get('/personas.xlsx', 'personasExcel')->name('personas.excel');
    });

    Route::get('/invitaciones', [InvitacionController::class, 'index'])->name('invitaciones.index');
    Route::resource('plantillas', PlantillaController::class)->except(['show', 'destroy']);
    Route::patch('/plantillas/{plantilla}/estado', [PlantillaController::class, 'estado'])->name('plantillas.estado');

    Route::resource('grupos', GrupoController::class)->except('destroy');
    Route::patch('/grupos/{grupo}/estado', [GrupoController::class, 'estado'])->name('grupos.estado');
    Route::delete('/grupos/{grupo}/contactos/{contacto}', [GrupoController::class, 'quitar'])->name('grupos.quitar');

    // Administración
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['show', 'destroy']);
    Route::patch('/usuarios/{usuario}/estado', [UserController::class, 'toggleActivo'])->name('usuarios.estado');

    Route::resource('roles', RoleController::class)
        ->parameters(['roles' => 'rol'])
        ->except('show');

    // Cuenta desde la que salen los correos
    Route::get('/configuracion/correo', [ConfiguracionCorreoController::class, 'edit'])->name('correo.edit');
    Route::put('/configuracion/correo', [ConfiguracionCorreoController::class, 'update'])->name('correo.update');
    Route::post('/configuracion/correo/prueba', [ConfiguracionCorreoController::class, 'probar'])
        ->middleware('throttle:10,1')->name('correo.probar');

    // Cuenta de WasenderAPI desde la que salen los mensajes de WhatsApp
    Route::get('/configuracion/whatsapp', [ConfiguracionWhatsappController::class, 'edit'])->name('whatsapp.edit');
    Route::put('/configuracion/whatsapp', [ConfiguracionWhatsappController::class, 'update'])->name('whatsapp.update');
    Route::post('/configuracion/whatsapp/prueba', [ConfiguracionWhatsappController::class, 'probar'])
        ->middleware('throttle:10,1')->name('whatsapp.probar');

    Route::middleware('permission:geografia.ver')->group(function () {
        Route::get('/geografia', [GeografiaController::class, 'index'])->name('geografia.index');
        Route::get('/geografia/{departamento}', [GeografiaController::class, 'show'])->name('geografia.show');
    });

    // Selector departamento → municipio de los formularios
    Route::get('/api/departamentos/{departamento}/municipios', [GeografiaController::class, 'municipios'])->name('api.municipios');
});
