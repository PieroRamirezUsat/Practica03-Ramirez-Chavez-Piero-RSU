<?php

namespace App\Http\Controllers\Api\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\Programacion;
use App\Models\PuntoRecorrido;
use App\Services\ServicioCiudadano;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Programaciones de la zona del ciudadano y seguimiento en vivo del camión. */
class ProgramacionController extends Controller
{
    public function __construct(private ServicioCiudadano $servicio) {}

    /** Lista del mes (por defecto el actual) de la zona del ciudadano. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['mes' => ['nullable', 'date_format:Y-m']], ['mes.date_format' => 'El mes debe tener el formato AAAA-MM.']);
        $ciudadano = $request->attributes->get('ciudadano');

        $mes = Carbon::createFromFormat('Y-m-d', ($datos['mes'] ?? today()->format('Y-m')).'-01')->startOfMonth();

        $lista = Programacion::with(ServicioCiudadano::RELACIONES)
            ->where('zone_id', $ciudadano->zone_id)
            ->whereBetween('date', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get()
            ->sortBy(fn (Programacion $p) => $p->inicio()->timestamp)
            ->values();

        return response()->json([
            'mes' => $mes->format('Y-m'),
            'ahora' => now()->toIso8601String(),
            'zona' => ['id' => $ciudadano->zone_id, 'nombre' => $ciudadano->zona?->name],
            'data' => $lista->map(fn (Programacion $p) => $this->servicio->resumen($p, $ciudadano))->all(),
        ]);
    }

    public function show(Request $request, int $programacion): JsonResponse
    {
        $ciudadano = $request->attributes->get('ciudadano');

        return response()->json(['data' => $this->servicio->detalle($this->delaZona($ciudadano->zone_id, $programacion), $ciudadano)]);
    }

    /**
     * Seguimiento en vivo (la app lo consulta cada pocos segundos).
     * `desde_id`: último punto que la app ya tiene; solo se devuelven los nuevos.
     */
    public function seguimiento(Request $request, int $programacion): JsonResponse
    {
        $datos = $request->validate(['desde_id' => ['nullable', 'integer', 'min:0']]);
        $ciudadano = $request->attributes->get('ciudadano');
        $p = $this->delaZona($ciudadano->zone_id, $programacion);
        $r = $p->recorrido;

        $puntos = collect();
        if ($r) {
            $puntos = PuntoRecorrido::where('trip_id', $r->id)
                ->where('id', '>', (int) ($datos['desde_id'] ?? 0))
                ->orderBy('id')
                ->limit(1000)
                ->get();
        }

        return response()->json([
            'ahora' => now()->toIso8601String(),
            'estado' => $r?->status ?? ($p->esProgramado() ? 'sin_iniciar' : 'finalizado'),
            'programacion' => $this->servicio->resumen($p, $ciudadano),
            'recorrido' => $r ? $this->servicio->recorridoBreve($r) : null,
            'zona_vertices' => $p->zona ? $p->zona->vertices() : [],
            'puntos' => $puntos->map(fn ($pt) => $this->servicio->punto($pt))->all(),
            'segundos_entre_puntos' => (int) config('proyecto.recorridos.segundos_entre_puntos', 5),
        ]);
    }

    private function delaZona(int $zonaId, int $id): Programacion
    {
        $p = Programacion::with(['zona.coordenadas', 'zona.sectores', 'turno', 'vehiculo', 'grupo', 'recorrido'])
            ->where('zone_id', $zonaId)
            ->find($id);

        abort_if(! $p, 404, 'No se encontró la programación.');

        return $p;
    }
}
