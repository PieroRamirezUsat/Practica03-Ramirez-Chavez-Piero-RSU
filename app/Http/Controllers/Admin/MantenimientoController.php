<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** PRÁCTICA 03 – Punto 2.a: mantenimientos programados (nombre, fecha de inicio y fecha de fin). */
class MantenimientoController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.mantenimientos.index');
        }

        return response()->json(['data' => Mantenimiento::withCount('horarios')->orderBy('start_date')->get()->map(fn (Mantenimiento $m) => $this->fila($m))]);
    }

    public function create()
    {
        return view('admin.mantenimientos.create');
    }

    public function store(Request $request)
    {
        $mantenimiento = Mantenimiento::create($this->validado($request));

        return $this->exito('Mantenimiento registrado', 'admin.mantenimientos.index', ['mantenimiento' => $this->fila($mantenimiento)]);
    }

    public function edit(string $id)
    {
        return view('admin.mantenimientos.edit', ['mantenimiento' => Mantenimiento::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $mantenimiento = Mantenimiento::withCount('horarios')->findOrFail($id);
        $datos = $this->validado($request, $mantenimiento->id);

        // Si ya tiene horarios, sus días se generaron con estas fechas: cambiarlas los dejaría fuera del rango.
        $cambianFechas = $datos['start_date'] !== $mantenimiento->start_date->toDateString()
            || $datos['end_date'] !== $mantenimiento->end_date->toDateString();

        if ($cambianFechas && $mantenimiento->horarios_count > 0) {
            throw ValidationException::withMessages(['start_date' => "No se pueden cambiar las fechas: el mantenimiento ya tiene {$mantenimiento->horarios_count} horario(s) con días generados."]);
        }

        $mantenimiento->update($datos);

        return $this->exito('Mantenimiento actualizado', 'admin.mantenimientos.index', ['mantenimiento' => $this->fila($mantenimiento)]);
    }

    /** a.i – Eliminación validada: la pantalla pide confirmación y aquí no se permite si todavía tiene horarios. */
    public function destroy(string $id)
    {
        $mantenimiento = Mantenimiento::withCount('horarios')->findOrFail($id);

        if ($mantenimiento->horarios_count > 0) {
            return $this->rechazo("No se puede eliminar: tiene {$mantenimiento->horarios_count} horario(s). Elimínelos primero.", 'admin.mantenimientos.index');
        }

        $mantenimiento->delete();

        return $this->exito('Mantenimiento eliminado', 'admin.mantenimientos.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('maintenances', 'name')->ignore($ignorarId)],
            'start_date' => ['required', 'date'],
            // a.ii: la fecha de inicio no puede ser mayor a la fecha de fin.
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'name.unique' => 'Ya existe un mantenimiento con ese nombre.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
        ], ['name' => 'nombre', 'start_date' => 'fecha de inicio', 'end_date' => 'fecha de fin']);

        // a.ii – Las fechas no se pueden solapar con otro mantenimiento (al editar, no se compara consigo mismo).
        $choque = Mantenimiento::queSeSolapanCon($datos['start_date'], $datos['end_date'])
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->first();

        if ($choque) {
            throw ValidationException::withMessages(['start_date' => "Las fechas se cruzan con «{$choque->name}» ({$choque->start_date->format('d/m/Y')} – {$choque->end_date->format('d/m/Y')})."]);
        }

        return $datos;
    }

    private function fila(Mantenimiento $m): array
    {
        return [
            'id' => $m->id,
            'nombre' => $m->name,
            'inicio' => $m->start_date->format('d/m/Y'),
            'fin' => $m->end_date->format('d/m/Y'),
            'horarios' => $m->horarios_count ?? $m->horarios()->count(),
            'url_horarios' => route('admin.mantenimientos.horarios.index', $m->id),
        ];
    }
}
