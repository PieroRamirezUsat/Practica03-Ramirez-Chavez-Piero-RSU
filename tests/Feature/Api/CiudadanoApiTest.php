<?php

namespace Tests\Feature\Api;

use App\Models\Ciudadano;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\PuntoRecorrido;
use App\Models\Recorrido;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\Vehiculo;
use App\Models\Zona;
use Database\Seeders\ColorVehiculoSeeder;
use Database\Seeders\MarcaSeeder;
use Database\Seeders\ModeloSeeder;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Database\Seeders\TurnoSeeder;
use Database\Seeders\VehiculoSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Avance 7: API de la app móvil del ciudadano (registro, zona, programaciones, seguimiento, historial y calificación). */
class CiudadanoApiTest extends TestCase
{
    use RefreshDatabase;

    private Zona $norte;

    private Zona $centro;

    private Personal $conductor;

    private int $dni = 70000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class, ZonaSeeder::class, MarcaSeeder::class, ModeloSeeder::class,
            TipoVehiculoSeeder::class, ColorVehiculoSeeder::class, VehiculoSeeder::class]);

        $this->travelTo(Carbon::parse('2026-11-10 07:00:00'));

        $this->norte = Zona::firstWhere('name', 'Zona Norte');
        $this->centro = Zona::firstWhere('name', 'Zona Centro');

        $tipo = TipoPersonal::conductor();
        $this->conductor = Personal::create([
            'dni' => '72000001', 'usertype_id' => $tipo->id, 'name' => 'Carlos', 'last_name' => 'Prueba', 'birthdate' => '1990-01-01',
            'email' => 'conductor@prueba.test', 'address' => 'Av. Principal 123', 'password' => 'secret123', 'status' => true, 'license' => 'Q72000001',
        ]);
        Contrato::create(['user_id' => $this->conductor->id, 'type' => Contrato::PERMANENTE, 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500, 'is_active' => true]);
    }

    private function ciudadano(?Zona $zona = null, array $cambios = []): Ciudadano
    {
        $this->dni++;

        return Ciudadano::create($cambios + [
            'dni' => (string) $this->dni, 'name' => 'Ana', 'last_name' => 'Vecina', 'email' => "c{$this->dni}@prueba.test",
            'password' => 'secret123', 'zone_id' => ($zona ?? $this->norte)->id, 'status' => true,
        ]);
    }

    private function token(Ciudadano $c): string
    {
        return $this->postJson('/api/ciudadano/login', ['email' => $c->email, 'password' => 'secret123'])->assertOk()->json('token');
    }

    private function api(string $metodo, string $url, string $token, array $datos = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->{$metodo.'Json'}('/api/ciudadano/'.$url, $datos);
    }

    private int $usadas = 0;

    /** Cada programación lleva su propio conductor y vehículo: la base no permite repetirlos en la misma fecha y turno. */
    private function programacion(?Zona $zona = null, ?string $fecha = null, string $turno = 'Mañana'): Programacion
    {
        $n = $this->usadas++;
        $this->dni++;
        $conductor = Personal::create([
            'dni' => (string) $this->dni, 'usertype_id' => TipoPersonal::conductor()->id, 'name' => 'Otro', 'last_name' => "Conductor {$this->dni}", 'birthdate' => '1990-01-01',
            'email' => "d{$this->dni}@prueba.test", 'address' => 'Av. Principal 123', 'password' => 'secret123', 'status' => true, 'license' => 'Q'.$this->dni,
        ]);

        return Programacion::create([
            'date' => $fecha ?? today()->toDateString(), 'zone_id' => ($zona ?? $this->norte)->id, 'shift_id' => Turno::firstWhere('name', $turno)->id,
            'vehicle_id' => Vehiculo::orderBy('id')->skip($n % 4)->value('id'), 'driver_id' => $conductor->id, 'status' => Programacion::PROGRAMADO,
        ])->fresh();
    }

    private function recorrido(Programacion $p, string $estado = Recorrido::COMPLETADO): Recorrido
    {
        $r = Recorrido::create([
            'programming_id' => $p->id, 'driver_id' => $this->conductor->id, 'status' => $estado,
            'started_at' => now()->subHour(), 'ended_at' => $estado === Recorrido::COMPLETADO ? now() : null,
        ]);
        foreach ([[-6.750, -79.840], [-6.752, -79.839]] as $i => [$lat, $lng]) {
            PuntoRecorrido::create(['trip_id' => $r->id, 'latitude' => $lat, 'longitude' => $lng, 'recorded_at' => now()->subMinutes(30 - $i)]);
        }

        return $r;
    }

    private function datosRegistro(array $cambios = []): array
    {
        return $cambios + ['dni' => '12345678', 'nombre' => 'María José', 'apellidos' => "O'Brien Pérez", 'email' => 'Maria@Prueba.test', 'password' => 'clave1234', 'zone_id' => $this->norte->id];
    }

    /* ------------------------------ registro y acceso ------------------------------ */

    public function test_el_ciudadano_se_registra_y_queda_con_sesion(): void
    {
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro())
            ->assertCreated()
            ->assertJsonStructure(['token', 'ciudadano' => ['id', 'dni', 'nombre_completo', 'zona' => ['id', 'nombre', 'sectores', 'vertices']]])
            ->assertJsonPath('ciudadano.correo', 'maria@prueba.test')
            ->assertJsonPath('ciudadano.zona.id', $this->norte->id);

        $this->assertDatabaseHas('citizens', ['dni' => '12345678', 'zone_id' => $this->norte->id]);
        $this->assertDatabaseMissing('users', ['email' => 'maria@prueba.test']);   // nunca entra a la tabla del personal
    }

    public function test_el_registro_valida_todos_los_campos(): void
    {
        $this->postJson('/api/ciudadano/registro', [])->assertStatus(422)->assertJsonValidationErrors(['dni', 'name', 'last_name', 'email', 'password', 'zone_id']);

        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['dni' => '1234']))->assertStatus(422)->assertJsonValidationErrors('dni');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['dni' => '1234567a']))->assertStatus(422)->assertJsonValidationErrors('dni');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['nombre' => 'Ana123']))->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['email' => 'no-es-correo']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['password' => 'corta']))->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['zone_id' => 9999]))->assertStatus(422)->assertJsonValidationErrors('zone_id');

        $this->norte->update(['status' => false]);
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro())->assertStatus(422)->assertJsonValidationErrors('zone_id');
    }

    public function test_no_se_repiten_dni_ni_correo(): void
    {
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro())->assertCreated();

        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['email' => 'otro@prueba.test']))->assertStatus(422)->assertJsonValidationErrors('dni');
        $this->postJson('/api/ciudadano/registro', $this->datosRegistro(['dni' => '87654321', 'email' => 'MARIA@prueba.test']))->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_ingreso_correcto_e_incorrecto(): void
    {
        $c = $this->ciudadano();

        $this->postJson('/api/ciudadano/login', ['email' => strtoupper($c->email), 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'ciudadano' => ['id', 'dni', 'zona']]);

        $this->postJson('/api/ciudadano/login', ['email' => $c->email, 'password' => 'otra-clave'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/ciudadano/login', ['email' => 'nadie@prueba.test', 'password' => 'secret123'])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');

        $inactivo = $this->ciudadano(null, ['status' => false]);
        $this->postJson('/api/ciudadano/login', ['email' => $inactivo->email, 'password' => 'secret123'])->assertStatus(422);
    }

    public function test_sin_token_o_con_token_de_conductor_no_se_entra(): void
    {
        $this->getJson('/api/ciudadano/programaciones')->assertUnauthorized();

        $tokenConductor = $this->postJson('/api/conductor/login', ['email' => 'conductor@prueba.test', 'password' => 'secret123'])->assertOk()->json('token');
        $this->api('get', 'programaciones', $tokenConductor)->assertForbidden();

        // y al revés: el token del ciudadano no abre la API del conductor
        $token = $this->token($this->ciudadano());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/conductor/programaciones')->assertForbidden();
    }

    public function test_un_ciudadano_deshabilitado_pierde_el_acceso(): void
    {
        $c = $this->ciudadano();
        $token = $this->token($c);
        $c->update(['status' => false]);

        $this->api('get', 'perfil', $token)->assertUnauthorized();
    }

    public function test_cerrar_sesion_invalida_el_token(): void
    {
        $token = $this->token($this->ciudadano());

        $this->api('post', 'logout', $token)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /* ------------------------------ zonas ------------------------------ */

    public function test_lista_publica_de_zonas_activas_con_sectores_y_foto(): void
    {
        $this->norte->sectores()->create(['name' => 'Sector Prueba', 'position' => 1]);
        $this->norte->update(['photo_path' => '/uploads/zone_photos/x.jpg']);
        $this->centro->update(['status' => false]);

        $r = $this->getJson('/api/ciudadano/zonas')->assertOk();

        $r->assertJsonCount(4, 'data');
        $norte = collect($r->json('data'))->firstWhere('id', $this->norte->id);
        $this->assertSame(['Sector Prueba'], $norte['sectores']);
        $this->assertStringEndsWith('/uploads/zone_photos/x.jpg', $norte['foto']);
        $this->assertNotContains($this->centro->id, collect($r->json('data'))->pluck('id')->all());
    }

    public function test_encuentra_la_zona_de_una_ubicacion(): void
    {
        $this->getJson('/api/ciudadano/zonas/ubicacion?lat=-6.754&lng=-79.8375')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->norte->id)->assertJsonPath('mensaje', null);
    }

    public function test_ubicacion_fuera_de_toda_zona_o_invalida(): void
    {
        $this->getJson('/api/ciudadano/zonas/ubicacion?lat=-12.0464&lng=-77.0428')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('mensaje', 'Su ubicación no está dentro de ninguna zona de recolección. Elija su zona en el mapa.');

        $this->getJson('/api/ciudadano/zonas/ubicacion')->assertStatus(422)->assertJsonValidationErrors(['lat', 'lng']);
        $this->getJson('/api/ciudadano/zonas/ubicacion?lat=abc&lng=500')->assertStatus(422)->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_en_el_borde_compartido_devuelve_las_dos_zonas(): void
    {
        $this->getJson('/api/ciudadano/zonas/ubicacion?lat=-6.7600&lng=-79.8375')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cambiar_de_zona(): void
    {
        $c = $this->ciudadano();
        $token = $this->token($c);

        $this->api('put', 'perfil/zona', $token, ['zone_id' => $this->centro->id])->assertOk()->assertJsonPath('ciudadano.zona.id', $this->centro->id);
        $this->assertSame($this->centro->id, $c->fresh()->zone_id);

        $this->api('put', 'perfil/zona', $token, ['zone_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->api('put', 'perfil/zona', $token, [])->assertStatus(422)->assertJsonValidationErrors('zone_id');
    }

    /* ------------------------------ programaciones ------------------------------ */

    public function test_solo_ve_las_programaciones_de_su_zona_y_del_mes(): void
    {
        $mia = $this->programacion($this->norte);
        $this->programacion($this->centro);
        $otroMes = $this->programacion($this->norte, '2026-12-05');
        $token = $this->token($this->ciudadano());

        $r = $this->api('get', 'programaciones', $token)->assertOk()->assertJsonPath('mes', '2026-11')->assertJsonCount(1, 'data');
        $this->assertSame($mia->id, $r->json('data.0.id'));
        $r->assertJsonPath('data.0.vehiculo.placa', Vehiculo::orderBy('id')->value('plate'));   // la primera programación usa el primer vehículo

        $r = $this->api('get', 'programaciones?mes=2026-12', $token)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($otroMes->id, $r->json('data.0.id'));

        $this->api('get', 'programaciones?mes=2026-13', $token)->assertStatus(422)->assertJsonValidationErrors('mes');
        $this->api('get', 'programaciones?mes=noviembre', $token)->assertStatus(422)->assertJsonValidationErrors('mes');
    }

    public function test_no_expone_datos_personales_del_personal(): void
    {
        $p = $this->programacion();
        $token = $this->token($this->ciudadano());

        $json = $this->api('get', 'programaciones', $token)->assertOk()->getContent();
        $this->api('get', "programaciones/{$p->id}", $token)->assertOk();

        foreach ([$p->conductor->dni, $p->conductor->email, $p->conductor->license, $p->conductor->last_name] as $dato) {
            $this->assertStringNotContainsString($dato, $json);
            $this->assertStringNotContainsString($dato, $this->api('get', "programaciones/{$p->id}", $token)->getContent());
        }
    }

    public function test_detalle_de_programacion_propia_y_ajena(): void
    {
        $mia = $this->programacion($this->norte);
        $ajena = $this->programacion($this->centro);
        $token = $this->token($this->ciudadano());

        $this->api('get', "programaciones/{$mia->id}", $token)->assertOk()
            ->assertJsonPath('data.id', $mia->id)->assertJsonPath('data.puede_seguir', false)->assertJsonStructure(['data' => ['zona_detalle' => ['vertices']]]);
        $this->api('get', "programaciones/{$ajena->id}", $token)->assertNotFound();
        $this->api('get', 'programaciones/99999', $token)->assertNotFound();
    }

    public function test_cambiar_de_zona_cambia_lo_que_se_ve(): void
    {
        $this->programacion($this->norte);
        $this->programacion($this->centro);
        $token = $this->token($this->ciudadano($this->norte));

        $this->api('put', 'perfil/zona', $token, ['zone_id' => $this->centro->id])->assertOk();
        $r = $this->api('get', 'programaciones', $token)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($this->centro->id, $r->json('data.0.zona.id'));
    }

    /* ------------------------------ seguimiento ------------------------------ */

    public function test_seguimiento_sin_iniciar_en_curso_y_con_puntos_nuevos(): void
    {
        $p = $this->programacion();
        $token = $this->token($this->ciudadano());

        $this->api('get', "programaciones/{$p->id}/seguimiento", $token)->assertOk()
            ->assertJsonPath('estado', 'sin_iniciar')->assertJsonPath('recorrido', null)->assertJsonCount(0, 'puntos');

        $r = $this->recorrido($p, Recorrido::EN_CURSO);
        $todos = $this->api('get', "programaciones/{$p->id}/seguimiento", $token)->assertOk()
            ->assertJsonPath('estado', 'en_curso')->assertJsonCount(2, 'puntos');
        $this->api('get', "programaciones/{$p->id}", $token)->assertJsonPath('data.puede_seguir', true);

        $ultimo = $todos->json('puntos.1.id');
        $this->api('get', "programaciones/{$p->id}/seguimiento?desde_id={$ultimo}", $token)->assertOk()->assertJsonCount(0, 'puntos');

        PuntoRecorrido::create(['trip_id' => $r->id, 'latitude' => -6.753, 'longitude' => -79.838, 'recorded_at' => now()]);
        $this->api('get', "programaciones/{$p->id}/seguimiento?desde_id={$ultimo}", $token)->assertOk()->assertJsonCount(1, 'puntos');
    }

    public function test_seguimiento_de_recorrido_completado_y_de_zona_ajena(): void
    {
        $p = $this->programacion();
        $this->recorrido($p);
        $ajena = $this->programacion($this->centro);
        $token = $this->token($this->ciudadano());

        $this->api('get', "programaciones/{$p->id}/seguimiento", $token)->assertOk()->assertJsonPath('estado', 'completado');
        $this->api('get', "programaciones/{$ajena->id}/seguimiento", $token)->assertNotFound();
        $this->api('get', "programaciones/{$p->id}/seguimiento?desde_id=-1", $token)->assertStatus(422);
    }

    /* ------------------------------ historial y calificación ------------------------------ */

    public function test_historial_solo_con_recorridos_completados_de_mi_zona(): void
    {
        $completado = $this->recorrido($this->programacion($this->norte, '2026-11-09'));
        $this->recorrido($this->programacion($this->norte, '2026-11-10', 'Tarde'), Recorrido::EN_CURSO);
        $this->recorrido($this->programacion($this->centro, '2026-11-08'));
        $token = $this->token($this->ciudadano());

        $r = $this->api('get', 'recorridos', $token)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($completado->id, $r->json('data.0.id'));
        $r->assertJsonPath('data.0.calificacion', null)->assertJsonPath('data.0.total_calificaciones', 0)->assertJsonPath('data.0.promedio', null);
    }

    public function test_detalle_de_recorrido_trae_trayectoria_y_respeta_la_zona(): void
    {
        $mio = $this->recorrido($this->programacion($this->norte, '2026-11-09'));
        $ajeno = $this->recorrido($this->programacion($this->centro, '2026-11-08'));
        $token = $this->token($this->ciudadano());

        $this->api('get', "recorridos/{$mio->id}", $token)->assertOk()->assertJsonCount(2, 'data.puntos')->assertJsonStructure(['data' => ['zona_vertices', 'segundos_entre_puntos']]);
        $this->api('get', "recorridos/{$ajeno->id}", $token)->assertNotFound();
    }

    public function test_califica_una_sola_vez_y_el_promedio_cuenta_a_todos(): void
    {
        $r = $this->recorrido($this->programacion());
        $a = $this->ciudadano();
        $b = $this->ciudadano();

        $this->api('post', "recorridos/{$r->id}/calificacion", $this->token($a), ['estrellas' => 5, 'comentario' => '  Excelente servicio  '])
            ->assertCreated()->assertJsonPath('data.calificacion.estrellas', 5)->assertJsonPath('data.calificacion.comentario', 'Excelente servicio');

        $this->api('post', "recorridos/{$r->id}/calificacion", $this->token($a), ['estrellas' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'Ya calificó este servicio.');
        $this->assertDatabaseCount('trip_ratings', 1);

        $this->api('post', "recorridos/{$r->id}/calificacion", $this->token($b), ['estrellas' => 3])->assertCreated()
            ->assertJsonPath('data.total_calificaciones', 2)->assertJsonPath('data.promedio', 4);
        $this->assertDatabaseHas('trip_ratings', ['trip_id' => $r->id, 'citizen_id' => $b->id, 'comment' => null]);
    }

    public function test_validaciones_de_la_calificacion(): void
    {
        $r = $this->recorrido($this->programacion());
        $token = $this->token($this->ciudadano());

        foreach ([[], ['estrellas' => 0], ['estrellas' => 6], ['estrellas' => 'mucho'], ['estrellas' => 2.5]] as $datos) {
            $this->api('post', "recorridos/{$r->id}/calificacion", $token, $datos)->assertStatus(422)->assertJsonValidationErrors('estrellas');
        }

        $this->api('post', "recorridos/{$r->id}/calificacion", $token, ['estrellas' => 4, 'comentario' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('comentario');
        $this->api('post', "recorridos/{$r->id}/calificacion", $token, ['estrellas' => 4, 'comentario' => str_repeat('a', 500)])->assertCreated();
    }

    public function test_no_se_califica_un_recorrido_en_curso_ni_de_otra_zona(): void
    {
        $enCurso = $this->recorrido($this->programacion($this->norte, '2026-11-10', 'Tarde'), Recorrido::EN_CURSO);
        $ajeno = $this->recorrido($this->programacion($this->centro, '2026-11-08'));
        $token = $this->token($this->ciudadano());

        $this->api('post', "recorridos/{$enCurso->id}/calificacion", $token, ['estrellas' => 5])->assertStatus(422);
        $this->api('post', "recorridos/{$ajeno->id}/calificacion", $token, ['estrellas' => 5])->assertNotFound();
        $this->api('post', 'recorridos/99999/calificacion', $token, ['estrellas' => 5])->assertNotFound();
        $this->assertDatabaseCount('trip_ratings', 0);
    }
}
