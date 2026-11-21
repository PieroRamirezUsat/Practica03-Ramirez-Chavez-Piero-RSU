<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** PRÁCTICA 03 – Mantenimiento programado (ej. «MANT. DICIEMBRE 2026», del 01/12/2026 al 31/12/2026). */
class Mantenimiento extends Model
{
    protected $table = 'maintenances';

    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioMantenimiento::class, 'maintenance_id')->orderBy('weekday')->orderBy('start_time');
    }

    /**
     * a.ii – Mantenimientos cuyas fechas se cruzan con [inicio, fin]: empiezan antes de que termine y terminan después
     * de que empiece. Con <= y >= compartir un solo día ya es solapar (las fechas son días completos).
     */
    public function scopeQueSeSolapanCon(Builder $consulta, string $inicio, string $fin): Builder
    {
        return $consulta->whereDate('start_date', '<=', $fin)->whereDate('end_date', '>=', $inicio);
    }
}
