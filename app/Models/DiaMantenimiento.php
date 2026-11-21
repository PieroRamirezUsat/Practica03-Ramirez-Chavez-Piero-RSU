<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PRÁCTICA 03 – Día generado de un horario (fecha, observación, imagen y si se realizó). */
class DiaMantenimiento extends Model
{
    protected $table = 'maintenance_days';

    protected $fillable = ['schedule_id', 'date', 'observation', 'image', 'status'];

    /** Estado => [texto, color de la insignia]. Un día nace «pendiente» (valor por defecto en la migración). */
    public const ESTADOS = [
        'pendiente' => ['Pendiente', 'ambar'],
        'realizado' => ['Realizado', 'verde'],
        'no_realizado' => ['No realizado', 'rojo'],
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(HorarioMantenimiento::class, 'schedule_id');
    }
}
