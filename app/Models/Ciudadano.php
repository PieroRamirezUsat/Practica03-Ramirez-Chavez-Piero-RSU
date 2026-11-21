<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Ciudadano de la app móvil. Vive en su propia tabla (no en users) para que nunca pueda entrar al panel web.
 * No usa Auditable: la bitácora guarda al autor como un users.id y un ciudadano no lo es.
 */
class Ciudadano extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'citizens';

    protected $fillable = ['dni', 'name', 'last_name', 'email', 'password', 'zone_id', 'status'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'status' => 'boolean'];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zone_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(CalificacionRecorrido::class, 'citizen_id');
    }

    public function nombreCompleto(): string
    {
        return trim($this->name.' '.$this->last_name);
    }
}
