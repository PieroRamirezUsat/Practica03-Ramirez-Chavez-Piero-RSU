<?php

namespace App\Services;

use App\Models\CalificacionRecorrido;
use App\Models\Ciudadano;
use App\Models\GrupoPersonal;
use App\Models\Programacion;
use App\Models\Recorrido;
use App\Models\Zona;

/**
 * Reglas y formato de datos de la app móvil del ciudadano (Avance 7).
 * Mismo formato que el de la app del conductor: fechas Y-m-d, horas H:i y momentos en ISO 8601.
 * El ciudadano no recibe datos personales del personal (solo zona, turno y vehículo).
 */
class ServicioCiudadano
{
    public const RELACIONES = ['zona', 'turno', 'vehiculo', 'grupo', 'recorrido'];

    /** ¿El punto está dentro del polígono? Vértices como [lat, lng]; el borde cuenta como dentro. */
    public function contiene(array $vertices, float $lat, float $lng): bool
    {
        $n = count($vertices);
        if ($n < 3) {
            return false;
        }

        $dentro = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$latI, $lngI] = $vertices[$i];
            [$latJ, $lngJ] = $vertices[$j];

            // ¿Está sobre este lado? (tolerancia de ~1 m)
            $cruz = ($lat - $latI) * ($lngJ - $lngI) - ($lng - $lngI) * ($latJ - $latI);
            if (abs($cruz) < 1e-9
                && $lat >= min($latI, $latJ) - 1e-9 && $lat <= max($latI, $latJ) + 1e-9
                && $lng >= min($lngI, $lngJ) - 1e-9 && $lng <= max($lngI, $lngJ) + 1e-9) {
                return true;
            }

            if ((($lngI > $lng) !== ($lngJ > $lng)) && ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI)) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /** Zonas activas que contienen la ubicación (las zonas pueden solaparse un poco, por eso puede haber más de una). */
    public function zonasEn(float $lat, float $lng)
    {
        return Zona::with(['coordenadas', 'sectores'])
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Zona $z) => $this->contiene($z->vertices(), $lat, $lng))
            ->values();
    }

    public function zona(Zona $z): array
    {
        return [
            'id' => $z->id,
            'nombre' => $z->name,
            'departamento' => $z->department,
            'provincia' => $z->province,
            'distrito' => $z->district,
            'descripcion' => $z->description,
            'foto' => $z->urlFoto(),
            'sectores' => $z->sectores->pluck('name')->values()->all(),
            'vertices' => $z->vertices(),
        ];
    }

    public function perfil(Ciudadano $c): array
    {
        $c->loadMissing('zona.coordenadas', 'zona.sectores');

        return [
            'id' => $c->id,
            'dni' => $c->dni,
            'nombre' => $c->name,
            'apellidos' => $c->last_name,
            'nombre_completo' => $c->nombreCompleto(),
            'correo' => $c->email,
            'zona' => $c->zona ? $this->zona($c->zona) : null,
        ];
    }

    /** Tarjeta de «Mis programaciones»: lo programado en la zona del ciudadano. */
    public function resumen(Programacion $p, ?Ciudadano $c = null): array
    {
        return [
            'id' => $p->id,
            'fecha' => $p->date->toDateString(),
            'estado' => $p->status,
            'zona' => ['id' => $p->zone_id, 'nombre' => $p->zona?->name],
            'vehiculo' => ['id' => $p->vehicle_id, 'nombre' => $p->vehiculo?->name, 'placa' => $p->vehiculo?->plate, 'codigo' => $p->vehiculo?->code],
            'turno' => [
                'id' => $p->shift_id,
                'nombre' => $p->turno?->name,
                'hora_inicio' => substr((string) $p->turno?->time_start, 0, 5),
                'hora_fin' => substr((string) $p->turno?->time_end, 0, 5),
            ],
            'inicio' => $p->inicio()->toIso8601String(),
            'fin' => $p->fin()->toIso8601String(),
            'dias' => $p->grupo ? array_map(fn (int $d) => GrupoPersonal::DIAS[$d] ?? (string) $d, $p->grupo->dias_ordenados) : [],
            'recorrido' => $p->recorrido ? $this->recorridoBreve($p->recorrido) : null,
        ];
    }

    public function detalle(Programacion $p, Ciudadano $c): array
    {
        return $this->resumen($p, $c) + [
            'zona_detalle' => $p->zona ? $this->zona($p->zona->loadMissing('coordenadas', 'sectores')) : null,
            'puede_seguir' => $p->recorrido?->enCurso() === true,
            'calificacion' => $p->recorrido ? $this->miCalificacion($p->recorrido, $c) : null,
        ];
    }

    public function recorridoBreve(Recorrido $r): array
    {
        return [
            'id' => $r->id,
            'estado' => $r->status,
            'estado_texto' => Recorrido::ESTADOS[$r->status] ?? $r->status,
            'inicio' => $r->started_at->toIso8601String(),
            'fin' => $r->ended_at?->toIso8601String(),
        ];
    }

    /** Fila del historial: recorrido completado de la zona, con mi calificación y el promedio de todos. */
    public function recorridoResumen(Recorrido $r, Ciudadano $c): array
    {
        $p = $r->programacion;
        $agregado = CalificacionRecorrido::where('trip_id', $r->id)->selectRaw('COUNT(*) as total, AVG(stars) as promedio')->first();

        return $this->recorridoBreve($r) + [
            'programacion_id' => $p->id,
            'fecha' => $p->date->toDateString(),
            'zona' => ['id' => $p->zone_id, 'nombre' => $p->zona?->name],
            'vehiculo' => ['id' => $p->vehicle_id, 'nombre' => $p->vehiculo?->name, 'placa' => $p->vehiculo?->plate, 'codigo' => $p->vehiculo?->code],
            'turno' => ['nombre' => $p->turno?->name, 'hora_inicio' => substr((string) $p->turno?->time_start, 0, 5), 'hora_fin' => substr((string) $p->turno?->time_end, 0, 5)],
            'calificacion' => $this->miCalificacion($r, $c),
            'total_calificaciones' => (int) ($agregado->total ?? 0),
            'promedio' => ($agregado->total ?? 0) > 0 ? round((float) $agregado->promedio, 1) : null,
        ];
    }

    /** Detalle del recorrido: trayectoria completa y zona. */
    public function recorridoDetalle(Recorrido $r, Ciudadano $c): array
    {
        $p = $r->programacion;

        return $this->recorridoResumen($r, $c) + [
            'zona_vertices' => $p->zona ? $p->zona->vertices() : [],
            'puntos' => $r->puntos->map(fn ($pt) => $this->punto($pt))->all(),
            'segundos_entre_puntos' => (int) config('proyecto.recorridos.segundos_entre_puntos', 5),
        ];
    }

    public function punto($pt): array
    {
        return ['id' => $pt->id, 'lat' => $pt->latitude, 'lng' => $pt->longitude, 'momento' => $pt->recorded_at->toIso8601String()];
    }

    public function miCalificacion(Recorrido $r, Ciudadano $c): ?array
    {
        $cal = CalificacionRecorrido::where('trip_id', $r->id)->where('citizen_id', $c->id)->first();

        return $cal ? ['estrellas' => $cal->stars, 'comentario' => $cal->comment, 'fecha' => $cal->created_at->toIso8601String()] : null;
    }
}
