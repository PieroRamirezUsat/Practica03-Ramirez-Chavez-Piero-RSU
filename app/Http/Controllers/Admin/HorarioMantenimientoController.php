<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\HorarioMantenimiento;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\Vehiculo;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** PRÁCTICA 03 – Punto 2.b: horarios de un mantenimiento y sus días generados. */
class HorarioMantenimientoController extends Controller
{
    use RespondeAjax;

    public function index(Mantenimiento $mantenimiento)
    {
        if (! request()->ajax()) {
            return view('admin.mantenimientos.horarios.index', ['mantenimiento' => $mantenimiento]);
        }

        $horarios = $mantenimiento->horarios()->with(['vehiculo', 'responsable'])->withCount('dias')->get();

        return response()->json(['data' => $horarios->map(fn (HorarioMantenimiento $h) => $this->fila($h))]);
    }

    public function create(Mantenimiento $mantenimiento)
    {
        return view('admin.mantenimientos.horarios.create', ['mantenimiento' => $mantenimiento] + $this->listas());
    }

    public function store(Request $request, Mantenimiento $mantenimiento)
    {
        $datos = $this->validado($request, $mantenimiento);

        // El horario y sus días se guardan juntos: si algo falla, no queda nada a medias.
        $horario = DB::transaction(function () use ($mantenimiento, $datos) {
            $horario = $mantenimiento->horarios()->create($datos);
            $this->generarDias($horario, $mantenimiento);

            return $horario;
        });

        return $this->exito("Horario registrado: se generaron {$horario->dias()->count()} día(s)", 'admin.mantenimientos.index');
    }

    public function edit(HorarioMantenimiento $horario)
    {
        return view('admin.mantenimientos.horarios.edit', ['horario' => $horario, 'mantenimiento' => $horario->mantenimiento] + $this->listas());
    }

    public function update(Request $request, HorarioMantenimiento $horario)
    {
        $mantenimiento = $horario->mantenimiento;
        $datos = $this->validado($request, $mantenimiento, $horario->id);
        $cambiaDia = $datos['weekday'] !== $horario->weekday;

        // Cambiar el día borra los días generados: no se permite si alguno ya tiene su resultado registrado.
        if ($cambiaDia && $horario->dias()->where('status', '!=', 'pendiente')->exists()) {
            throw ValidationException::withMessages(['weekday' => 'No se puede cambiar el día: ya hay días con su resultado registrado (se perderían sus observaciones e imágenes).']);
        }

        DB::transaction(function () use ($horario, $datos, $mantenimiento, $cambiaDia) {
            $horario->update($datos);

            // Solo si cambió el día de la semana se rehacen los días; si no, se conservan con lo que ya tengan anotado.
            if ($cambiaDia) {
                $this->borrarDias($horario);
                $this->generarDias($horario, $mantenimiento);
            }
        });

        return $this->exito($cambiaDia ? 'Horario actualizado: se regeneraron sus días' : 'Horario actualizado', 'admin.mantenimientos.index');
    }

    /** b.ii – Elimina el horario y sus días generados (la pantalla ya pidió confirmación indicando cuántos días son). */
    public function destroy(HorarioMantenimiento $horario)
    {
        $total = $horario->dias()->count();

        DB::transaction(function () use ($horario) {
            $this->borrarDias($horario);
            $horario->delete();
        });

        return $this->exito("Horario eliminado junto con sus {$total} día(s) generados", 'admin.mantenimientos.index');
    }

    /**
     * b.i – Fechas del mantenimiento que caen en el día de la semana elegido.
     * Ej.: del 01/12/2026 al 31/12/2026 y lunes → 07, 14, 21 y 28 (el 01/12/2026 es martes).
     */
    private function fechasDelDia(Mantenimiento $mantenimiento, int $dia): Collection
    {
        return collect(CarbonPeriod::create($mantenimiento->start_date, $mantenimiento->end_date))
            ->filter(fn ($fecha) => $fecha->dayOfWeekIso === $dia);
    }

    private function generarDias(HorarioMantenimiento $horario, Mantenimiento $mantenimiento): void
    {
        foreach ($this->fechasDelDia($mantenimiento, $horario->weekday) as $fecha) {
            $horario->dias()->create(['date' => $fecha->toDateString()]);   // nace «pendiente»
        }
    }

    /** Borra los días del horario y sus imágenes (borrarArchivo es del trait RespondeAjax). */
    private function borrarDias(HorarioMantenimiento $horario): void
    {
        $horario->dias()->get()->each(fn ($dia) => $this->borrarArchivo($dia->image));
        $horario->dias()->delete();
    }

    private function validado(Request $request, Mantenimiento $mantenimiento, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            // Vehículo y responsable traídos de la BD del proyecto (activos; el responsable debe ser del Personal).
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('status', true)],
            'responsible_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNotNull('usertype_id')->where('status', true)],
            'type' => ['required', Rule::in(array_keys(HorarioMantenimiento::TIPOS))],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ], [
            'vehicle_id' => 'vehículo', 'responsible_id' => 'responsable', 'type' => 'tipo de mantenimiento',
            'weekday' => 'día de la semana', 'start_time' => 'hora de inicio', 'end_time' => 'hora de fin',
        ]);

        $datos['weekday'] = (int) $datos['weekday'];
        $datos['start_time'] .= ':00';   // se guarda y se compara siempre como "08:00:00"
        $datos['end_time'] .= ':00';

        // El día elegido debe existir dentro del rango del mantenimiento (si no, no se generaría ningún día).
        if ($this->fechasDelDia($mantenimiento, $datos['weekday'])->isEmpty()) {
            throw ValidationException::withMessages(['weekday' => 'Ese día de la semana no cae dentro de las fechas del mantenimiento.']);
        }

        // b.iii – No solapar: mismo día + horas que se cruzan (al editar, no se compara consigo mismo)...
        $cruzados = fn () => $mantenimiento->horarios()
            ->queChocanCon($datos['weekday'], $datos['start_time'], $datos['end_time'])
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId));

        // ...para el mismo vehículo (lo pide la práctica)...
        if ($choque = $cruzados()->where('vehicle_id', $datos['vehicle_id'])->first()) {
            throw ValidationException::withMessages(['start_time' => "Se cruza con otro horario de ese vehículo: {$choque->nombre_dia} de {$choque->hora_inicio} a {$choque->hora_fin}."]);
        }

        // ...y para el mismo responsable (una persona no puede estar en dos mantenimientos a la vez).
        if ($choque = $cruzados()->where('responsible_id', $datos['responsible_id'])->first()) {
            throw ValidationException::withMessages(['responsible_id' => "El responsable ya tiene otro mantenimiento el {$choque->nombre_dia} de {$choque->hora_inicio} a {$choque->hora_fin}."]);
        }

        return $datos;
    }

    /** Opciones de los <select> del formulario. */
    private function listas(): array
    {
        return [
            'vehiculos' => Vehiculo::where('status', true)->orderBy('name')->get(),
            'responsables' => Personal::activos()->orderBy('name')->get(),
            'tipos' => HorarioMantenimiento::TIPOS,
            'dias' => HorarioMantenimiento::DIAS,
        ];
    }

    private function fila(HorarioMantenimiento $h): array
    {
        return [
            'id' => $h->id,
            'dia' => $h->nombre_dia,
            'vehiculo' => $h->vehiculo?->name,
            'responsable' => $h->responsable?->nombre_completo,
            'tipo' => $h->nombre_tipo,
            'inicio' => Carbon::parse($h->start_time)->format('h:i a'),   // "08:00 am", como en el PDF
            'fin' => Carbon::parse($h->end_time)->format('h:i a'),
            'dias' => $h->dias_count,
            'url_dias' => route('admin.mantenimientos.dias.index', $h->id),
        ];
    }
}
