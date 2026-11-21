<?php

namespace App\Http\Middleware;

use App\Models\Ciudadano;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API del ciudadano es solo para ciudadanos activos con token de la habilidad «ciudadano».
 * Un token de conductor no sirve aquí (ni uno de ciudadano en la API del conductor).
 */
class SoloCiudadano
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Ciudadano || ! $usuario->tokenCan('ciudadano')) {
            return response()->json(['message' => 'Esta sección es solo para ciudadanos.'], 403);
        }

        if (! $usuario->status) {
            $usuario->currentAccessToken()?->delete();

            return response()->json(['message' => 'Su cuenta fue deshabilitada. Consulte con la municipalidad.'], 401);
        }

        $request->attributes->set('ciudadano', $usuario);

        return $next($request);
    }
}
