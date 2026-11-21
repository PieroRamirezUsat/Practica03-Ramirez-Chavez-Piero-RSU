<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\DiaMantenimiento;
use App\Models\HorarioMantenimiento;
use Illuminate\Http\Request;

/** PRÁCTICA 03 – Punto 2.c: opción VER de un horario. Cada día se edita con observación, imagen y si se realizó. */
class DiaMantenimientoController extends Controller
{
    use RespondeAjax;

    public function index(HorarioMantenimiento $horario)
    {
        if (! request()->ajax()) {
            return view('admin.mantenimientos.dias.index', ['horario' => $horario->load(['mantenimiento', 'vehiculo', 'responsable'])]);
        }

        return response()->json(['data' => $horario->dias()->get()->map(fn (DiaMantenimiento $d) => $this->fila($d))]);
    }

    public function edit(DiaMantenimiento $dia)
    {
        return view('admin.mantenimientos.dias.edit', ['dia' => $dia, 'imagen' => $dia->image ? $this->urlArchivo($dia->image) : null]);
    }

    public function update(Request $request, DiaMantenimiento $dia)
    {
        $datos = $request->validate([
            'observation' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'done' => ['nullable', 'boolean'],
        ], [], ['observation' => 'observación', 'image' => 'imagen', 'done' => 'mantenimiento realizado']);

        $cambios = [
            'observation' => $datos['observation'] ?? null,
            // Casilla marcada → realizado; sin marcar → no realizado (un día que nunca se editó sigue «pendiente»).
            'status' => $request->boolean('done') ? 'realizado' : 'no_realizado',
        ];

        // Igual que la foto del Personal: conArchivoNuevo (trait RespondeAjax) guarda la nueva en public/uploads y borra la anterior.
        $this->conArchivoNuevo($request->file('image'), 'mantenimientos', function (?string $ruta) use ($dia, $cambios) {
            $dia->update($ruta ? $cambios + ['image' => $ruta] : $cambios);
        }, $dia->image);

        return $this->exito('Día actualizado', 'admin.mantenimientos.index');
    }

    private function fila(DiaMantenimiento $d): array
    {
        [$estado, $color] = DiaMantenimiento::ESTADOS[$d->status];

        return [
            'id' => $d->id,
            'fecha' => $d->date->format('d/m/Y'),
            'observacion' => $d->observation,
            'imagen' => $d->image ? $this->urlArchivo($d->image) : null,
            'estado' => $estado,
            'color' => $color,
        ];
    }
}
