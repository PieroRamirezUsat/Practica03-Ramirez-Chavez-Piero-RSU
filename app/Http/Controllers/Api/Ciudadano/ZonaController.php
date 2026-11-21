<?php

namespace App\Http\Controllers\Api\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use App\Services\ServicioCiudadano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Zonas visibles antes de tener cuenta: lista y «encontrar mi ubicación». */
class ZonaController extends Controller
{
    public function __construct(private ServicioCiudadano $servicio) {}

    public function index(): JsonResponse
    {
        $zonas = Zona::with(['coordenadas', 'sectores'])->where('status', true)->orderBy('name')->get();

        return response()->json(['data' => $zonas->map(fn (Zona $z) => $this->servicio->zona($z))->all()]);
    }

    /** Zonas que contienen la ubicación. Puede haber varias (las zonas se solapan un poco) o ninguna. */
    public function ubicacion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $zonas = $this->servicio->zonasEn((float) $datos['lat'], (float) $datos['lng']);

        return response()->json([
            'data' => $zonas->map(fn (Zona $z) => $this->servicio->zona($z))->all(),
            'mensaje' => $zonas->isEmpty() ? 'Su ubicación no está dentro de ninguna zona de recolección. Elija su zona en el mapa.' : null,
        ]);
    }
}
