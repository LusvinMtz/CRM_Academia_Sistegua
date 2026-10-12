<?php

namespace Tests\Feature;

use App\Mail\ConstanciaCorreo;
use App\Models\Contacto;
use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Sede;
use App\Models\User;
use App\Services\InvitacionesEvento;
use Database\Seeders\CertificadosSeeder;
use Database\Seeders\GeografiaSeeder;
use Database\Seeders\PlantillasSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\SedesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class Fase5Test extends TestCase
{
    use RefreshDatabase;

    private Sede $guatemala;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GeografiaSeeder::class, SedesSeeder::class, RolesPermisosSeeder::class, PlantillasSeeder::class, CertificadosSeeder::class]);
        Mail::fake();
        $this->guatemala = Sede::where('nombre', 'Ciudad de Guatemala')->firstOrFail();
        $this->guatemala->update(['direccion' => 'Barrio El Centro, Sanarate']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(User::ROL_ADMINISTRADOR);
    }

    private function evento(string $tipo = Evento::CAPACITACION, array $datos = []): Evento
    {
        return Evento::create($datos + [
            'tipo' => $tipo, 'titulo' => 'Taller de evaluación',
            'sede_id' => $this->guatemala->id, 'modalidad' => Evento::PRESENCIAL, 'lugar' => 'Salón', 'para_todos' => true,
            'inicio' => now()->subHour(), 'fin' => now()->addHour(), 'facilitador' => 'Lic. Ana Pérez',
        ]);
    }

    private function contacto(string $tipo = Contacto::CLIENTE, array $datos = []): Contacto
    {
        return Contacto::create($datos + [
            'tipo' => $tipo, 'sede_id' => $this->guatemala->id, 'nombres' => 'Nombre'.uniqid(), 'apellidos' => 'Apellido',
            'correo' => uniqid().'@correo.com',
        ]);
    }

    private function segmento(Evento $e): string
    {
        return Evento::segmentoDe($e->tipo);
    }

    public function test_lista_incluye_destinatarios_sin_correo_y_guarda_asistencia(): void
    {
        $evento = $this->evento();
        $conCorreo = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Ana']);
        $sinCorreo = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Luis', 'correo' => null]);
        (new InvitacionesEvento($evento))->invitar('A', 'M', $this->admin);
        Invitacion::where('contacto_id', $conCorreo->id)->update(['respuesta' => Invitacion::CONFIRMADA]);

        $this->actingAs($this->admin)->get(route('asistencia.show', ['capacitaciones', $evento]))
            ->assertOk()->assertSee('Ana')->assertSee('Luis')->assertSee('Sin correo');

        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $evento]), [
            'contactos' => [$conCorreo->id, $sinCorreo->id],
            'presentes' => [$sinCorreo->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '1 persona presente'));

        $this->assertFalse(Invitacion::where('contacto_id', $conCorreo->id)->value('asistio'));
        $registro = Invitacion::where('contacto_id', $sinCorreo->id)->sole();
        $this->assertTrue($registro->asistio);
        $this->assertSame(Invitacion::NO_ENVIADA, $registro->estado_envio);
        $this->assertSame($this->admin->id, $registro->asistencia_por);

        // El registro de solo asistencia no cuenta como invitación enviada
        $this->actingAs($this->admin)->get(route('eventos.show', ['capacitaciones', $evento]))->assertOk()->assertSee('Control del evento')->assertSee('Llegaron sin invitación');
    }

    public function test_sin_marcar_no_cuenta_como_ausente(): void
    {
        $evento = $this->evento();
        $presente = $this->contacto();
        $ausente = $this->contacto();
        $sinMarcar = $this->contacto();
        foreach ([[$presente, true], [$ausente, false], [$sinMarcar, null]] as [$c, $asistio]) {
            Invitacion::create(['evento_id' => $evento->id, 'contacto_id' => $c->id, 'correo' => $c->correo, 'estado_envio' => 'enviada', 'asistio' => $asistio]);
        }

        $resumen = $this->actingAs($this->admin)->get(route('asistencia.show', ['capacitaciones', $evento]))->viewData('resumen');
        $this->assertSame(1, $resumen['presentes']);
        $this->assertSame(1, $resumen['ausentes']);
        $this->assertSame(1, $resumen['sin_marcar']);
    }

    public function test_agregar_asistente_que_llego_sin_invitacion(): void
    {
        $evento = $this->evento(Evento::CAPACITACION, ['para_todos' => false]); // sin destinatarios
        $visitante = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Pedro']);
        $otraSede = $this->contacto(Contacto::CLIENTE, ['sede_id' => Sede::where('nombre', 'Chiquimula')->value('id')]);

        $this->actingAs($this->admin)->getJson(route('asistencia.buscar', ['capacitaciones', $evento, 'q' => 'Pedro']))
            ->assertOk()->assertJsonFragment(['id' => $visitante->id]);

        $this->actingAs($this->admin)->post(route('asistencia.agregar', ['capacitaciones', $evento]), ['contacto_id' => $visitante->id])
            ->assertSessionHas('success');
        $this->assertTrue(Invitacion::where('contacto_id', $visitante->id)->value('asistio'));

        $this->actingAs($this->admin)->post(route('asistencia.agregar', ['capacitaciones', $evento]), ['contacto_id' => $otraSede->id])
            ->assertSessionHasErrors('contacto_id');

        // Ya aparece en la lista y no en la búsqueda
        $this->actingAs($this->admin)->getJson(route('asistencia.buscar', ['capacitaciones', $evento, 'q' => 'Pedro']))->assertJsonCount(0);
    }

    public function test_no_se_marca_asistencia_antes_del_dia_ni_en_cancelados(): void
    {
        $futuro = $this->evento(Evento::CAPACITACION, ['inicio' => now()->addDays(3), 'fin' => now()->addDays(3)->addHour()]);
        $c = $this->contacto();

        $this->actingAs($this->admin)->get(route('asistencia.show', ['capacitaciones', $futuro]))->assertOk()->assertSee('desde el día de la actividad');
        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $futuro]), ['contactos' => [$c->id], 'presentes' => [$c->id]])
            ->assertStatus(422);

        $cancelado = $this->evento();
        $cancelado->forceFill(['cancelado_at' => now()])->save();
        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $cancelado]), ['contactos' => [$c->id], 'presentes' => [$c->id]])
            ->assertStatus(422);
        $this->assertSame(0, Invitacion::count());
    }

    public function test_si_luego_registra_correo_se_puede_invitar(): void
    {
        $evento = $this->evento(Evento::CAPACITACION, ['inicio' => now()->addHours(2), 'fin' => now()->addHours(4)]);
        $sinCorreo = $this->contacto(Contacto::CLIENTE, ['correo' => null]);
        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $evento]), ['contactos' => [$sinCorreo->id], 'presentes' => []]);

        $sinCorreo->update(['correo' => 'ahora@correo.com']);
        $this->assertSame(1, (new InvitacionesEvento($evento))->invitar('A', 'M', $this->admin));
        $this->assertSame(Invitacion::ENVIADA, Invitacion::sole()->estado_envio);
    }

    public function test_lista_para_firmas_y_excel_de_asistencia(): void
    {
        $evento = $this->evento();
        $c = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Rosa', 'apellidos' => 'Morales', 'empresa' => 'Sofía']);
        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $evento]), ['contactos' => [$c->id], 'presentes' => [$c->id]]);

        $pdf = $this->actingAs($this->admin)->get(route('asistencia.pdf', ['capacitaciones', $evento]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAs($this->admin)->get(route('asistencia.excel', ['capacitaciones', $evento]));
        $xlsx->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'asi').'.xlsx';
        file_put_contents($ruta, $xlsx->streamedContent());
        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame('Rosa Morales', $filas[4][1]);
        $this->assertSame('Sofía', $filas[4][2]);
        $this->assertSame('Presente', $filas[4][7]);
    }

    public function test_constancias_pdf_verificacion_y_envio(): void
    {
        $taller = $this->evento(Evento::CAPACITACION);
        $asistio = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Luis', 'apellidos' => 'Juárez']);
        $falto = $this->contacto(Contacto::CLIENTE);
        $this->actingAs($this->admin)->post(route('asistencia.guardar', ['capacitaciones', $taller]), [
            'contactos' => [$asistio->id, $falto->id], 'presentes' => [$asistio->id],
        ]);
        $invAsistio = Invitacion::where('contacto_id', $asistio->id)->sole();
        $invFalto = Invitacion::where('contacto_id', $falto->id)->sole();

        $pdf = $this->actingAs($this->admin)->get(route('constancias.descargar', [$taller, $invAsistio]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $codigo = $invAsistio->fresh()->codigo_constancia;
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codigo);

        // Sin asistencia no hay constancia; las reuniones no tienen constancias
        $this->actingAs($this->admin)->get(route('constancias.descargar', [$taller, $invFalto]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('constancias.todas', $this->evento()))->assertNotFound();

        $this->actingAs($this->admin)->get(route('constancias.todas', $taller))->assertOk();

        // Verificación pública
        $this->get(route('constancia.verificar', $codigo))->assertOk()->assertSee('Constancia válida')->assertSee('Luis Juárez');
        $this->get(route('constancia.verificar', 'XXXX-XXXX'))->assertOk()->assertSee('Código no encontrado');

        $this->actingAs($this->admin)->post(route('constancias.enviar', $taller))->assertSessionHas('success', fn ($m) => str_contains($m, '1 constancias'));
        Mail::assertQueued(ConstanciaCorreo::class, fn ($m) => $m->hasTo($asistio->correo));
        Mail::assertNotQueued(ConstanciaCorreo::class, fn ($m) => $m->hasTo($falto->correo));
    }

    public function test_reportes_por_evento_y_por_persona(): void
    {
        $pasado = $this->evento(Evento::CAPACITACION, ['inicio' => now()->subDays(2), 'fin' => now()->subDays(2)->addHours(2)]);
        $ana = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Ana']);
        $beto = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Beto']);
        Invitacion::create(['evento_id' => $pasado->id, 'contacto_id' => $ana->id, 'correo' => $ana->correo, 'estado_envio' => 'enviada', 'respuesta' => 'confirmada', 'asistio' => true]);
        Invitacion::create(['evento_id' => $pasado->id, 'contacto_id' => $beto->id, 'correo' => $beto->correo, 'estado_envio' => 'enviada', 'asistio' => false]);

        $this->actingAs($this->admin)->get(route('reportes.eventos'))
            ->assertOk()->assertSee('Taller de evaluación')->assertSee('50%');

        $resp = $this->actingAs($this->admin)->get(route('reportes.personas'));
        $resp->assertOk()->assertSeeInOrder([$beto->nombre_completo, $ana->nombre_completo]); // primero quien menos asistió

        $xlsx = $this->actingAs($this->admin)->get(route('reportes.eventos.excel'));
        $ruta = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($ruta, $xlsx->streamedContent());
        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame('Taller de evaluación', $filas[4][1]);
        $this->assertEquals(2, $filas[4][5]);   // invitaciones enviadas
        $this->assertEquals(1, $filas[4][6]);   // confirmadas
        $this->assertEquals(1, $filas[4][7]);   // no confirmadas
        $this->assertEquals(1, $filas[4][9]);   // asistencias
        $this->assertEquals(1, $filas[4][10]);  // inasistencias
        $this->assertSame('50%', $filas[4][11]);

        $this->actingAs($this->admin)->get(route('reportes.personas.excel'))->assertOk();

        // Historial en la ficha del contacto
        $this->actingAs($this->admin)->get(route('contactos.edit', ['clientes', $ana]))->assertOk()->assertSee('Historial de participación')->assertSee('Asistió');
    }

    public function test_reporte_historial_por_persona(): void
    {
        $ana = $this->contacto(Contacto::CLIENTE, ['nombres' => 'Ana']);
        $cumplio = $this->evento(Evento::CAPACITACION, ['titulo' => 'Curso cumplido', 'inicio' => now()->subDays(10), 'fin' => now()->subDays(10)->addHours(2)]);
        $falto = $this->evento(Evento::CAPACITACION, ['titulo' => 'Curso fallado', 'inicio' => now()->subDays(5), 'fin' => now()->subDays(5)->addHours(2)]);
        $proximo = $this->evento(Evento::CAPACITACION, ['titulo' => 'Curso próximo', 'inicio' => now()->addDays(5), 'fin' => now()->addDays(5)->addHours(2)]);
        Invitacion::create(['evento_id' => $cumplio->id, 'contacto_id' => $ana->id, 'correo' => $ana->correo, 'estado_envio' => 'enviada', 'respuesta' => 'confirmada', 'asistio' => true]);
        Invitacion::create(['evento_id' => $falto->id, 'contacto_id' => $ana->id, 'correo' => $ana->correo, 'estado_envio' => 'enviada', 'respuesta' => 'confirmada', 'asistio' => false]);
        Invitacion::create(['evento_id' => $proximo->id, 'contacto_id' => $ana->id, 'correo' => $ana->correo, 'estado_envio' => 'enviada']);

        // Sin filtrar ya muestra a cada persona con sus capacitaciones
        $this->actingAs($this->admin)->get(route('reportes.historial'))
            ->assertOk()->assertSeeInOrder([$ana->nombre_completo, 'de 2 asistidas', '2 confirmó', 'Curso próximo', 'Curso fallado', 'Confirmó y faltó', 'Curso cumplido']);
        $this->actingAs($this->admin)->get(route('reportes.historial', ['buscar' => 'Nadie Existe']))
            ->assertOk()->assertDontSee('Curso cumplido');

        $xlsx = $this->actingAs($this->admin)->get(route('reportes.historial.excel'));
        $ruta = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($ruta, $xlsx->streamedContent());
        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame($ana->nombre_completo, $filas[4][0]);
        $this->assertSame('Confirmó y asistió', $filas[6][10]);

        $this->actingAs($this->admin)->get(route('reportes.historial', ['contacto' => $ana->id]))
            ->assertOk()
            ->assertSeeInOrder(['Curso próximo', 'Aún no se realiza', 'Curso fallado', 'Confirmó y faltó', 'Curso cumplido', 'Confirmó y asistió'])
            ->assertSee('50% de las invitaciones')
            ->assertSee('1 después de confirmar');

        $xlsx = $this->actingAs($this->admin)->get(route('reportes.historial.excel', ['contacto' => $ana->id]));
        $ruta = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($ruta, $xlsx->streamedContent());
        $filas = IOFactory::load($ruta)->getSheet(0)->toArray();
        $this->assertSame('Curso próximo', $filas[4][1]);
        $this->assertSame('Confirmó y faltó', $filas[5][8]);
        $this->assertSame('Asistió', $filas[6][7]);

        // Un director de otra sede no puede ver el historial
        $otro = User::factory()->create(['sede_id' => Sede::whereKeyNot($ana->sede_id)->value('id')]);
        $otro->assignRole('Director de sede');
        $this->actingAs($otro)->get(route('reportes.historial', ['contacto' => $ana->id]))->assertNotFound();
    }

    public function test_permisos_de_asistencia_y_reportes(): void
    {
        $evento = $this->evento();
        $secretaria = User::factory()->create();
        $secretaria->assignRole('Secretaría');

        $this->actingAs($secretaria)->get(route('asistencia.show', ['capacitaciones', $evento]))->assertOk();
        $this->actingAs($secretaria)->get(route('reportes.eventos'))->assertOk();
        $this->actingAs($secretaria)->get(route('reportes.eventos.excel'))->assertForbidden();

        $directorCoban = User::factory()->create(['sede_id' => Sede::where('nombre', 'Chiquimula')->value('id')]);
        $directorCoban->assignRole('Director de sede');
        $this->actingAs($directorCoban)->get(route('asistencia.show', ['capacitaciones', $evento]))->assertForbidden();
        $this->actingAs($directorCoban)->get(route('reportes.eventos'))->assertOk()->assertDontSee('Reunión general');
    }
}
