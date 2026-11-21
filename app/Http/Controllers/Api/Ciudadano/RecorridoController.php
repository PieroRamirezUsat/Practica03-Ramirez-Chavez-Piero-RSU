<?php

namespace App\Http\Controllers\Api\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\CalificacionRecorrido;
use App\Models\Recorrido;
use App\Services\ServicioCiudadano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Historial de recorridos completados de la zona del ciudadano, su detalle y la calificación del servicio. */
class RecorridoController extends Controller
{
    private const RELACIONES = ['programacion.zona', 'programacion.turno', 'programacion.vehiculo'];

    public function __construct(private ServicioCiudadano $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $ciudadano = $request->attributes->get('ciudadano');

        $pagina = Recorrido::with(self::RELACIONES)
            ->where('status', Recorrido::COMPLETADO)
            ->whereHas('programacion', fn ($q) => $q->where('zone_id', $ciudadano->zone_id))
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Recorrido $r) => $this->servicio->recorridoResumen($r, $ciudadano))->all(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    public function show(Request $request, int $recorrido): JsonResponse
    {
        $ciudadano = $request->attributes->get('ciudadano');
        $r = $this->delaZona($ciudadano->zone_id, $recorrido)->load(['programacion.zona.coordenadas', 'puntos']);

        return response()->json(['data' => $this->servicio->recorridoDetalle($r, $ciudadano)]);
    }

    /** Califica el servicio (1 a 5 estrellas y comentario opcional). Una sola vez por recorrido. */
    public function calificar(Request $request, int $recorrido): JsonResponse
    {
        $maximo = (int) config('proyecto.recorridos.observacion_max', 500);
        $datos = $request->validate([
            'estrellas' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', "max:{$maximo}"],
        ], [
            'estrellas.required' => 'Elija de 1 a 5 estrellas.',
            'estrellas.integer' => 'Elija de 1 a 5 estrellas.',
            'estrellas.between' => 'Elija de 1 a 5 estrellas.',
            'comentario.max' => "El comentario no puede pasar de {$maximo} caracteres.",
        ]);

        $ciudadano = $request->attributes->get('ciudadano');
        $comentario = trim((string) ($datos['comentario'] ?? ''));

        DB::transaction(function () use ($ciudadano, $recorrido, $datos, $comentario) {
            // Bloquea el recorrido: dos toques seguidos en «Enviar» no crean dos calificaciones.
            $this->delaZona($ciudadano->zone_id, $recorrido);
            $r = Recorrido::lockForUpdate()->findOrFail($recorrido);

            abort_if($r->status !== Recorrido::COMPLETADO, 422, 'Solo se puede calificar un recorrido ya completado.');
            abort_if(CalificacionRecorrido::where('trip_id', $r->id)->where('citizen_id', $ciudadano->id)->exists(), 422, 'Ya calificó este servicio.');

            CalificacionRecorrido::create([
                'trip_id' => $r->id,
                'citizen_id' => $ciudadano->id,
                'stars' => (int) $datos['estrellas'],
                'comment' => $comentario === '' ? null : $comentario,
            ]);
        });

        $r = $this->delaZona($ciudadano->zone_id, $recorrido);

        return response()->json(['message' => 'Gracias por calificar el servicio.', 'data' => $this->servicio->recorridoResumen($r, $ciudadano)], 201);
    }

    private function delaZona(int $zonaId, int $id): Recorrido
    {
        $r = Recorrido::with(self::RELACIONES)
            ->whereHas('programacion', fn ($q) => $q->where('zone_id', $zonaId))
            ->find($id);

        abort_if(! $r, 404, 'No se encontró el recorrido.');

        return $r;
    }
}
