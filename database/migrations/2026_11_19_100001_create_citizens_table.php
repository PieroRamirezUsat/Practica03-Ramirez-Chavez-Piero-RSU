<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ciudadanos de la app móvil: tabla propia, separada de users, para que nunca entren al panel web. */
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('dni', 8)->unique();
            $tabla->string('name', 100);
            $tabla->string('last_name', 100);
            $tabla->string('email', 150)->unique();
            $tabla->string('password');
            $tabla->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $tabla->boolean('status')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizens');
    }
};
