<?php

namespace Tests\Feature;

use App\Models\ColorVehiculo;
use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** PRÁCTICA 03 – Una prueba por requisito del enunciado.  php artisan test --filter=MantenimientoTest */
class MantenimientoTest extends TestCase
{
    use RefreshDatabase;

    private Vehiculo $vehiculoA;

    private Vehiculo $vehiculoB;

    private Personal $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->vehiculoA = $this->vehiculo('Vehículo A', 'AAA-111');
        $this->vehiculoB = $this->vehiculo('Vehículo B', 'BBB-222');
        $this->responsable = Personal::create([
            'dni' => '40000001', 'usertype_id' => TipoPersonal::conductor()->id, 'name' => 'Juan', 'last_name' => 'Pérez',
            'birthdate' => '1990-01-01', 'email' => 'juan.perez@prueba.test', 'address' => 'Av. Principal 123',
            'password' => 'secret123', 'status' => true, 'license' => 'Q40000001',
        ]);
    }

    private function vehiculo(string $nombre, string $placa): Vehiculo
    {
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);

        return Vehiculo::create([
            'name' => $nombre, 'code' => 'VEH-'.substr($placa, 0, 3), 'plate' => $placa, 'year' => 2022,
            'load_capacity' => 10, 'fuel_capacity' => 150, 'compact_capacity' => 6, 'occupant_capacity' => 3, 'status' => true,
            'brand_id' => $marca->id,
            'model_id' => Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-01'])->id,
            'type_id' => TipoVehiculo::firstOrCreate(['name' => 'Compactador'])->id,
            'color_id' => ColorVehiculo::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF'])->id,
        ]);
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    private function diciembre(): Mantenimiento
    {
        return Mantenimiento::create(['name' => 'MANT. DICIEMBRE 2026', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31']);
    }

    private function horario(Mantenimiento $m, array $cambios = [])
    {
        return $this->ajax('post', route('admin.mantenimientos.horarios.store', $m->id), $cambios + [
            'vehicle_id' => $this->vehiculoA->id, 'responsible_id' => $this->responsable->id, 'type' => 'preventivo',
            'weekday' => 1, 'start_time' => '08:00', 'end_time' => '11:00',
        ]);
    }

    private function fechasDe(HorarioMantenimiento $h): array
    {
        return $h->dias()->get()->map(fn ($d) => $d->date->format('d/m'))->all();
    }

    /* ------------------------------------------------------------------ 2.a mantenimientos */

    public function test_registra_y_lista_mantenimientos(): void
    {
        $this->ajax('post', route('admin.mantenimientos.store'), ['name' => 'MANT. DICIEMBRE 2026', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31'])->assertOk();

        $this->ajax('get', route('admin.mantenimientos.index'))
            ->assertJsonPath('data.0.nombre', 'MANT. DICIEMBRE 2026')->assertJsonPath('data.0.inicio', '01/12/2026')->assertJsonPath('data.0.horarios', 0);
    }

    public function test_valida_las_fechas_del_mantenimiento(): void
    {
        $url = route('admin.mantenimientos.store');

        // Inicio mayor que fin → rechazado. El mismo día sí vale.
        $this->ajax('post', $url, ['name' => 'Al revés', 'start_date' => '2026-12-31', 'end_date' => '2026-12-01'])->assertStatus(422)->assertJsonValidationErrors('end_date');
        $this->ajax('post', $url, ['name' => 'Un día', 'start_date' => '2026-12-05', 'end_date' => '2026-12-05'])->assertOk();

        // Fecha imposible → rechazada.
        $this->ajax('post', $url, ['name' => 'X', 'start_date' => '2027-02-30', 'end_date' => '2027-03-01'])->assertStatus(422);
    }

    public function test_las_fechas_de_los_mantenimientos_no_se_solapan(): void
    {
        $diciembre = $this->diciembre();
        $url = route('admin.mantenimientos.store');

        $this->ajax('post', $url, ['name' => 'Cruzado', 'start_date' => '2026-12-15', 'end_date' => '2027-01-15'])->assertStatus(422)->assertJsonValidationErrors('start_date');
        $this->ajax('post', $url, ['name' => 'Comparte el 31', 'start_date' => '2026-12-31', 'end_date' => '2027-01-31'])->assertStatus(422);
        $this->ajax('post', $url, ['name' => 'MANT. ENERO 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31'])->assertOk();

        // Al editar no choca consigo mismo.
        $this->ajax('put', route('admin.mantenimientos.update', $diciembre->id), ['name' => 'MANT. DIC 2026', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31'])->assertOk();
    }

    public function test_eliminar_y_cambiar_fechas_estan_validados_si_tiene_horarios(): void
    {
        $vacio = Mantenimiento::create(['name' => 'Vacío', 'start_date' => '2027-03-01', 'end_date' => '2027-03-31']);
        $this->ajax('delete', route('admin.mantenimientos.destroy', $vacio->id))->assertOk();
        $this->assertModelMissing($vacio);

        $diciembre = $this->diciembre();
        $this->horario($diciembre)->assertOk();

        $this->ajax('delete', route('admin.mantenimientos.destroy', $diciembre->id))->assertStatus(422);
        $this->assertModelExists($diciembre);

        $this->ajax('put', route('admin.mantenimientos.update', $diciembre->id), ['name' => 'DIC', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15'])->assertStatus(422);
        $this->ajax('put', route('admin.mantenimientos.update', $diciembre->id), ['name' => 'DIC', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31'])->assertOk();   // solo el nombre: sí
    }

    /* ------------------------------------------------------------------ 2.b horarios */

    public function test_al_registrar_un_horario_se_generan_sus_dias(): void
    {
        $diciembre = $this->diciembre();

        $this->horario($diciembre, ['weekday' => 1])->assertOk();   // lunes: el 01/12/2026 es martes
        $this->assertSame(['07/12', '14/12', '21/12', '28/12'], $this->fechasDe(HorarioMantenimiento::latest('id')->first()));

        $this->horario($diciembre, ['weekday' => 2])->assertOk();   // martes: las fechas del ejemplo del PDF
        $this->assertSame(['01/12', '08/12', '15/12', '22/12', '29/12'], $this->fechasDe(HorarioMantenimiento::latest('id')->first()));

        $this->assertSame(9, DiaMantenimiento::where('status', 'pendiente')->count());
    }

    public function test_los_horarios_no_se_solapan_en_dia_hora_y_vehiculo(): void
    {
        $diciembre = $this->diciembre();
        $this->horario($diciembre)->assertOk();   // lunes 08–11, vehículo A

        $this->horario($diciembre, ['start_time' => '10:00', 'end_time' => '11:00'])->assertStatus(422)->assertJsonValidationErrors('start_time');   // ejemplo del PDF
        $this->horario($diciembre, ['start_time' => '07:00', 'end_time' => '12:00'])->assertStatus(422);
        $this->horario($diciembre, ['start_time' => '08:00', 'end_time' => '11:00'])->assertStatus(422);   // duplicado exacto

        $this->horario($diciembre, ['start_time' => '11:00', 'end_time' => '13:00'])->assertOk();   // solo se tocan
        $this->horario($diciembre, ['start_time' => '13:00', 'end_time' => '15:00'])->assertOk();
        $this->horario($diciembre, ['weekday' => 3, 'start_time' => '10:00', 'end_time' => '11:00'])->assertOk();   // otro día

        // Otro vehículo a la misma hora: con OTRO responsable sí; con el mismo responsable no (no puede estar en dos lugares).
        $otro = Personal::create(['dni' => '40000002', 'usertype_id' => TipoPersonal::ayudante()->id, 'name' => 'Daniel', 'last_name' => 'Rojas',
            'birthdate' => '1992-01-01', 'email' => 'daniel@prueba.test', 'address' => 'Calle 1', 'password' => 'secret123', 'status' => true]);
        $this->horario($diciembre, ['vehicle_id' => $this->vehiculoB->id, 'start_time' => '10:00', 'end_time' => '11:00'])->assertStatus(422)->assertJsonValidationErrors('responsible_id');
        $this->horario($diciembre, ['vehicle_id' => $this->vehiculoB->id, 'responsible_id' => $otro->id, 'start_time' => '10:00', 'end_time' => '11:00'])->assertOk();

        $this->assertSame(5, HorarioMantenimiento::count());
    }

    public function test_valida_los_datos_del_horario(): void
    {
        $diciembre = $this->diciembre();

        $this->horario($diciembre, ['start_time' => '11:00', 'end_time' => '08:00'])->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->horario($diciembre, ['type' => 'pintura'])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->horario($diciembre, ['weekday' => 8])->assertStatus(422)->assertJsonValidationErrors('weekday');
        $this->horario($diciembre, ['vehicle_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('vehicle_id');
        $this->horario($diciembre, ['responsible_id' => auth()->id()])->assertStatus(422)->assertJsonValidationErrors('responsible_id');   // un admin no es Personal

        // Del martes 01/06 al jueves 03/06/2027 no hay ningún lunes.
        $corto = Mantenimiento::create(['name' => 'Corto', 'start_date' => '2027-06-01', 'end_date' => '2027-06-03']);
        $this->horario($corto, ['weekday' => 1])->assertStatus(422)->assertJsonValidationErrors('weekday');
    }

    public function test_editar_un_horario_conserva_o_regenera_sus_dias(): void
    {
        $diciembre = $this->diciembre();
        $this->horario($diciembre)->assertOk();
        $horario = HorarioMantenimiento::first();
        $horario->dias()->first()->update(['observation' => 'Todo conforme', 'status' => 'realizado']);

        $datos = ['vehicle_id' => $this->vehiculoA->id, 'responsible_id' => $this->responsable->id, 'type' => 'limpieza', 'start_time' => '09:00', 'end_time' => '12:00'];

        // Mismo día (y no choca consigo mismo) → los días se conservan.
        $this->ajax('put', route('admin.mantenimientos.horarios.update', $horario->id), $datos + ['weekday' => 1])->assertOk();
        $this->assertSame('Todo conforme', $horario->dias()->first()->observation);

        // Cambiar el día con un día ya registrado (realizado) → rechazado, para no perder la evidencia.
        $this->ajax('put', route('admin.mantenimientos.horarios.update', $horario->id), $datos + ['weekday' => 3])->assertStatus(422)->assertJsonValidationErrors('weekday');

        // Si todos siguen pendientes, cambiar a miércoles regenera los días.
        $horario->dias()->update(['status' => 'pendiente']);
        $this->ajax('put', route('admin.mantenimientos.horarios.update', $horario->id), $datos + ['weekday' => 3])->assertOk();
        $this->assertSame(['02/12', '09/12', '16/12', '23/12', '30/12'], $this->fechasDe($horario));
    }

    public function test_eliminar_un_horario_elimina_sus_dias(): void
    {
        $this->horario($this->diciembre())->assertOk();
        $horario = HorarioMantenimiento::first();

        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', $horario->id))
            ->assertOk()->assertJsonPath('mensaje', 'Horario eliminado junto con sus 4 día(s) generados');

        $this->assertModelMissing($horario);
        $this->assertSame(0, DiaMantenimiento::count());
    }

    public function test_no_se_elimina_un_vehiculo_ni_una_persona_con_mantenimientos(): void
    {
        $this->horario($this->diciembre())->assertOk();

        $this->ajax('delete', route('admin.vehiculos.destroy', $this->vehiculoA->id))->assertStatus(422);
        $this->ajax('delete', route('admin.personal.destroy', $this->responsable->id))->assertStatus(422);
        $this->assertModelExists($this->vehiculoA);
        $this->assertModelExists($this->responsable);
    }

    /* ------------------------------------------------------------------ 2.c días */

    public function test_cada_dia_se_edita_con_observacion_imagen_y_si_se_realizo(): void
    {
        $this->horario($this->diciembre())->assertOk();   // lunes: 07, 14, 21 y 28/12
        $horario = HorarioMantenimiento::first();
        [$dia07, $dia14] = $horario->dias()->get()->all();

        // Realizado, con observación e imagen.
        $this->ajax('put', route('admin.mantenimientos.dias.update', $dia07->id), ['observation' => 'Todo conforme', 'done' => 1, 'image' => UploadedFile::fake()->image('motor.jpg')])->assertOk();
        $dia07->refresh();
        $this->assertSame(['Todo conforme', 'realizado'], [$dia07->observation, $dia07->status]);
        $this->assertFileExists(public_path(ltrim($dia07->image, '/')));

        // Sin marcar la casilla → «No realizado».
        $this->ajax('put', route('admin.mantenimientos.dias.update', $dia14->id), ['observation' => 'No se realizó', 'done' => 0])->assertOk();
        $this->assertSame('no_realizado', $dia14->fresh()->status);

        // Validaciones de los campos.
        $url = route('admin.mantenimientos.dias.update', $dia07->id);
        $this->ajax('put', $url, ['observation' => str_repeat('a', 501), 'done' => 1])->assertStatus(422)->assertJsonValidationErrors('observation');
        $this->ajax('put', $url, ['done' => 1, 'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->assertNotNull($dia07->fresh()->image);   // los intentos fallidos no tocaron la imagen

        $this->ajax('get', route('admin.mantenimientos.dias.index', $horario->id))->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.estado', 'Realizado')->assertJsonPath('data.1.estado', 'No realizado')->assertJsonPath('data.2.estado', 'Pendiente');

        @unlink(public_path(ltrim($dia07->fresh()->image, '/')));
    }

    public function test_las_pantallas_cargan(): void
    {
        $diciembre = $this->diciembre();
        $this->horario($diciembre)->assertOk();
        $this->flushHeaders();   // las siguientes son peticiones normales (HTML), no AJAX

        $this->get(route('admin.mantenimientos.index'))->assertOk()->assertSee('Mantenimientos programados');
        $this->get(route('admin.mantenimientos.horarios.index', $diciembre->id))->assertOk()->assertSee('MANT. DICIEMBRE 2026 / Horarios');
        $this->get(route('admin.mantenimientos.dias.index', HorarioMantenimiento::first()->id))->assertOk()->assertSee('Días programados');
        $this->get(route('admin.mantenimientos.horarios.create', $diciembre->id))->assertOk()->assertSee('Vehículo A')->assertSee('Juan Pérez');
    }
}
