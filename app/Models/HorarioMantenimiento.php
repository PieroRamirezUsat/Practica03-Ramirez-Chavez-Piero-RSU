<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** PRÁCTICA 03 – Horario de un mantenimiento: vehículo, tipo, día de la semana, horas y responsable. */
class HorarioMantenimiento extends Model
{
    protected $table = 'maintenance_schedules';

    protected $fillable = ['maintenance_id', 'vehicle_id', 'responsible_id', 'type', 'weekday', 'start_time', 'end_time'];

    public const TIPOS = ['preventivo' => 'Preventivo', 'limpieza' => 'Limpieza', 'reparacion' => 'Reparación'];

    /** Numeración ISO: 1 = lunes … 7 = domingo (la misma que da Carbon con dayOfWeekIso). */
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class, 'maintenance_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehicle_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'responsible_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(DiaMantenimiento::class, 'schedule_id')->orderBy('date');
    }

    /** La BD guarda "08:00:00"; el <input type="time"> usa "08:00". */
    public function getHoraInicioAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getHoraFinAttribute(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function getNombreDiaAttribute(): string
    {
        return self::DIAS[$this->weekday] ?? '—';
    }

    public function getNombreTipoAttribute(): string
    {
        return self::TIPOS[$this->type] ?? $this->type;
    }

    /**
     * b.iii – Horarios del MISMO día cuyas horas se cruzan (inicio < otroFin y fin > otroInicio).
     * Con < y > estrictos, 08:00–11:00 y 11:00–13:00 no chocan (solo se tocan); 08:00–11:00 y 10:00–11:00 sí.
     * El controlador le agrega el filtro por vehículo (lo pide la práctica) y por responsable.
     */
    public function scopeQueChocanCon(Builder $consulta, int $dia, string $inicio, string $fin): Builder
    {
        return $consulta->where('weekday', $dia)
            ->where('start_time', '<', $fin)
            ->where('end_time', '>', $inicio);
    }
}
