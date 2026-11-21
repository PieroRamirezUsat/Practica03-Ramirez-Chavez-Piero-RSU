<?php

namespace App\Http\Middleware;

use App\Models\Citizen;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Guardia de la API ciudadana v1 (modelo Citizen). La API de /api/ciudadano usa SoloCiudadano. */
class SoloCiudadanoV1
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Citizen
            && $usuario->status
            && $usuario->currentAccessToken() instanceof PersonalAccessToken
            && $usuario->tokenCan('ciudadano'), 403);

        return $next($request);
    }
}
