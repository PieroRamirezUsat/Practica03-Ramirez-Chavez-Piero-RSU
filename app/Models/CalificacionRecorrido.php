<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Calificación de un ciudadano a un recorrido completado (una sola por ciudadano y recorrido). */
class CalificacionRecorrido extends Model
{
    protected $table = 'trip_ratings';

    protected $fillable = ['trip_id', 'citizen_id', 'stars', 'comment'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }

    public function recorrido(): BelongsTo
    {
        return $this->belongsTo(Recorrido::class, 'trip_id');
    }

    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'citizen_id');
    }
}
