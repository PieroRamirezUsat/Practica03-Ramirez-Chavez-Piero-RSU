<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Calificación (1 a 5 estrellas) de un ciudadano a un recorrido completado; una sola vez por recorrido. */
    public function up(): void
    {
        Schema::create('trip_ratings', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('trip_id')->constrained('trips')->restrictOnDelete();
            $tabla->foreignId('citizen_id')->constrained('citizens')->restrictOnDelete();
            $tabla->unsignedTinyInteger('rating');
            $tabla->string('comment', 500)->nullable();
            $tabla->timestamps();

            $tabla->unique(['trip_id', 'citizen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ratings');
    }
};
