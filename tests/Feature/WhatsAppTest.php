<?php

namespace Tests\Feature;

use App\Models\ConfiguracionWhatsapp;
use App\Models\Contacto;
use App\Models\Envio;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Models\User;
use App\Services\WhatsApp;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\PlantillasSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private User $admin;
    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, PlantillasSeeder::class]);
        Mail::fake();
        $this->sede = Sede::where('nombre', 'Ciudad de Guatemala')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
        $this->evento = Evento::create([
            'tipo' => Evento::CAPACITACION, 'titulo' => 'Instalación de tabla yeso', 'sede_id' => $this->sede->id,
            'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Salón principal', 'para_todos' => true,
            'inicio' => now()->addWeek()->setTime(15, 0), 'fin' => now()->addWeek()->setTime(17, 0),
        ]);
    }

    private function configurar(array $respuesta = ['success' => true, 'data' => ['msgId' => 1, 'status' => 'in_progress']], int $status = 200): void
    {
        Http::fake(['*/send-message' => Http::response($respuesta, $status)]);
        ConfiguracionWhatsapp::create(['modo' => ConfiguracionWhatsapp::API, 'token' => 'clave-secreta', 'codigo_pais' => '502', 'pausa_segundos' => 0]);
    }

    private function cliente(array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'sede_id' => $this->sede->id, 'nombres' => 'María', 'apellidos' => 'García',
            'correo' => uniqid().'@correo.com', 'telefono' => '5874-3210',
        ]);
    }

    private function invitar(string $canal)
    {
        return $this->actingAs($this->admin)->post(route('invitaciones.enviar', ['capacitaciones', $this->evento]), [
            'asunto' => 'Invitación: {titulo}', 'mensaje' => 'Estimado(a) {nombre}: le esperamos.', 'canal' => $canal,
        ]);
    }

    public function test_numero_en_formato_internacional(): void
    {
        $w = new WhatsApp(new ConfiguracionWhatsapp(['codigo_pais' => '502']));

        $this->assertSame('+50258743210', $w->numero('5874-3210'));
        $this->assertSame('+50258743210', $w->numero('+502 5874 3210'));
        $this->assertNull($w->numero('1234'));
        $this->assertNull($w->numero(null));
    }

    public function test_guarda_la_configuracion_con_el_token_cifrado(): void
    {
        $this->actingAs($this->admin)->get(route('whatsapp.edit'))->assertOk()->assertSee('Cuenta de WasenderAPI');

        $this->actingAs($this->admin)->put(route('whatsapp.update'), [
            'modo' => 'log', 'token' => ' abc123 ', 'codigo_pais' => '502', 'pausa_segundos' => 5,
        ])->assertRedirect(route('whatsapp.edit'))->assertSessionHasNoErrors();

        $this->assertSame('abc123', ConfiguracionWhatsapp::first()->token);
        $this->assertNotSame('abc123', DB::table('configuracion_whatsapp')->value('token'));

        // En blanco conserva el token guardado
        $this->actingAs($this->admin)->put(route('whatsapp.update'), ['modo' => 'log', 'token' => '', 'codigo_pais' => '502', 'pausa_segundos' => 5]);
        $this->assertSame('abc123', ConfiguracionWhatsapp::first()->token);

        $u = User::factory()->create();
        $u->assignRole('Secretaría');
        $this->actingAs($u)->get(route('whatsapp.edit'))->assertForbidden();
    }

    public function test_invitar_por_whatsapp_envia_con_el_token_y_el_enlace(): void
    {
        $this->configurar();
        $maria = $this->cliente();
        $this->cliente(['telefono' => null]);                   // sin teléfono: no se invita por WhatsApp

        $this->invitar('whatsapp')->assertSessionHas('success', fn ($m) => str_contains($m, '1 invitaciones por WhatsApp'));

        $inv = Invitacion::sole();
        $this->assertSame($maria->id, $inv->contacto_id);
        $this->assertSame(Invitacion::ENVIADA, $inv->estado_envio);
        $this->assertSame(Invitacion::ENVIADA, $inv->whatsapp_estado);
        $this->assertNull($inv->correo_estado);
        $this->assertSame('whatsapp', Envio::sole()->canal);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer clave-secreta')
            && $r['to'] === '+50258743210'
            && str_contains($r['text'], 'María García')
            && str_contains($r['text'], $inv->url()));
        Mail::assertNothingQueued();

        // Los canales son independientes: por correo todavía se puede invitar a las dos
        $this->invitar('correo')->assertSessionHas('success', fn ($m) => str_contains($m, '2 invitaciones por correo'));
        $this->assertSame(Invitacion::ENVIADA, $inv->fresh()->correo_estado);

        // Y no se repite el WhatsApp
        $this->invitar('whatsapp')->assertSessionHas('info');
        Http::assertSentCount(1);
    }

    public function test_error_de_wasender_queda_registrado(): void
    {
        $this->configurar(['success' => false, 'message' => 'Invalid token'], 401);
        $this->cliente();

        $this->invitar('whatsapp');

        $inv = Invitacion::sole();
        $this->assertSame(Invitacion::FALLIDA, $inv->estado_envio);
        $this->assertStringContainsString('rechazó el token', $inv->error);
    }

    public function test_sin_token_no_envia(): void
    {
        ConfiguracionWhatsapp::create(['modo' => ConfiguracionWhatsapp::API]);
        $this->cliente();

        $this->invitar('whatsapp')->assertSessionHas('error', fn ($m) => str_contains($m, 'token'));
        $this->assertSame(0, Invitacion::count());
    }

    public function test_modo_de_prueba_no_llama_a_la_api(): void
    {
        Http::fake();
        $this->cliente();

        $this->invitar('whatsapp')->assertSessionHas('success', fn ($m) => str_contains($m, 'Modo de prueba'));

        $this->assertSame(Invitacion::ENVIADA, Invitacion::sole()->whatsapp_estado);
        Http::assertNothingSent();
    }

    public function test_recordatorio_y_aviso_por_el_canal_de_la_invitacion(): void
    {
        $this->configurar();
        $this->cliente();
        $this->invitar('whatsapp');

        $this->actingAs($this->admin)->post(route('invitaciones.recordar', ['capacitaciones', $this->evento]), [
            'incluir' => ['sin_respuesta'], 'canal' => 'whatsapp',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'por WhatsApp'));
        Http::assertSentCount(2);

        // El aviso de cancelación va solo por WhatsApp, que es por donde se invitó
        (new \App\Services\InvitacionesEvento($this->evento))->avisar('cancelacion', $this->admin);
        Http::assertSentCount(3);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_proteccion_espacia_los_mensajes_al_azar_y_en_una_sola_fila(): void
    {
        $config = ConfiguracionWhatsapp::create(['pausa_segundos' => 2, 'proteccion' => true]);
        foreach (range(1, 20) as $i) {
            $this->assertThat($config->espacioEntreMensajes(), $this->logicalAnd($this->greaterThanOrEqual(5), $this->lessThanOrEqual(10)));
        }
        $config->update(['proteccion' => false]);
        $this->assertSame(2, $config->espacioEntreMensajes());

        // Turnos consecutivos (aunque vengan de envíos distintos) nunca coinciden
        $config->update(['proteccion' => true, 'pausa_segundos' => 8]);
        $turnos = collect(range(1, 5))->map(fn () => WhatsApp::turno());
        $turnos->sliding(2)->each(function ($par) {
            $espera = $par->first()->diffInSeconds($par->last());
            $this->assertTrue($espera >= 8 && $espera <= 16, "Espera de {$espera} s");
        });

        // Con la protección no se acepta una pausa menor de 5 segundos
        $this->actingAs($this->admin)->put(route('whatsapp.update'), [
            'modo' => 'log', 'codigo_pais' => '502', 'pausa_segundos' => 2, 'proteccion' => '1',
        ])->assertSessionHasErrors('pausa_segundos');
    }

    public function test_solo_se_escribe_a_clientes_activos(): void
    {
        $this->configurar();
        $activo = $this->cliente();
        $inactivo = $this->cliente(['nombres' => 'Pedro']);
        $this->invitar('whatsapp');
        Http::assertSentCount(2);

        // Se desactiva después de invitado: ya no recibe recordatorios, avisos ni reenvíos
        $inactivo->update(['activo' => false]);
        $this->actingAs($this->admin)->post(route('invitaciones.recordar', ['capacitaciones', $this->evento]), [
            'incluir' => ['sin_respuesta'], 'canal' => 'whatsapp',
        ]);
        Http::assertSentCount(3); // solo a la activa

        $inv = Invitacion::where('contacto_id', $inactivo->id)->sole();
        $this->actingAs($this->admin)->post(route('invitaciones.reenviar', ['capacitaciones', $this->evento, $inv]), ['canal' => 'whatsapp'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'inactivo'));
        Http::assertSentCount(3);

        (new \App\Services\InvitacionesEvento($this->evento))->avisar('cancelacion', $this->admin);
        Http::assertSentCount(4); // solo a la activa
        Http::assertSent(fn (Request $r) => $r['to'] === '+50258743210' && str_contains($r['text'], $activo->nombres));
    }

    public function test_el_sistema_procesa_la_cola_sin_tarea_programada(): void
    {
        config(['queue.default' => 'database']);
        $this->configurar();
        ConfiguracionWhatsapp::first()->update(['proteccion' => false, 'pausa_segundos' => 0]);
        $this->cliente();
        $this->cliente(['nombres' => 'Pedro']);

        $this->invitar('whatsapp');
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertSame(0, \App\Services\ProcesadorEnvios::segundosHastaElSiguiente());
        Http::assertNothingSent();

        $this->assertSame(2, (new \App\Services\ProcesadorEnvios())->procesar(30));

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNull(\App\Services\ProcesadorEnvios::segundosHastaElSiguiente());
        $this->assertSame(2, Invitacion::where('whatsapp_estado', Invitacion::ENVIADA)->count());
        Http::assertSentCount(2);
    }

    public function test_constancia_por_whatsapp_con_enlace_firmado(): void
    {
        $this->configurar();
        $this->seed(\Database\Seeders\CertificadosSeeder::class);
        $c = $this->cliente();
        $inv = Invitacion::registrarAsistencia($this->evento, $c, true, $this->admin->id, 'manual');

        $this->actingAs($this->admin)->post(route('constancias.enviar', $this->evento), ['canal' => 'whatsapp'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 constancias por WhatsApp'));

        $url = null;
        Http::assertSent(function (Request $r) use (&$url) {
            $url = $r['documentUrl'];

            return str_ends_with($r['fileName'], '.pdf') && str_contains($url, 'signature=');
        });

        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('constancia.publica', $inv->token))->assertForbidden(); // sin firma
    }
}
