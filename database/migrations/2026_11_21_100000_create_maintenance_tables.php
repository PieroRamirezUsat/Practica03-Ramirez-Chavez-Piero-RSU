<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRÁCTICA 03 – Mantenimiento de vehículos.
 *
 * Tres tablas encadenadas (padre → hijo → detalle):
 *
 *   maintenances            Mantenimiento: «MANT. DICIEMBRE 2026», del 01/12/2026 al 31/12/2026.
 *     └ maintenance_schedules   Horario: vehículo, tipo, día de la semana, hora inicio/fin y responsable.
 *         └ maintenance_days        Días generados automáticamente por cada horario (fecha, observación, imagen, estado).
 *
 * Borrar un horario borra sus días (cascadeOnDelete). Un mantenimiento con horarios NO se borra: lo impide el controlador
 * (eliminación validada) y además la base de datos (restrictOnDelete), por si alguien lo intenta por otro camino.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Mantenimiento: nombre y rango de fechas (las reglas de fechas y solapamiento se validan en el controlador).
        Schema::create('maintenances', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->date('start_date');
            $tabla->date('end_date');
            $tabla->timestamps();
        });

        // 2) Horario del mantenimiento.
        Schema::create('maintenance_schedules', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('maintenance_id')->constrained('maintenances')->restrictOnDelete();
            $tabla->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            // El responsable es una persona del módulo Personal (tabla users con usertype_id).
            $tabla->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $tabla->string('type', 20);                       // preventivo | limpieza | reparacion
            $tabla->unsignedTinyInteger('weekday');           // 1 = lunes … 7 = domingo (norma ISO-8601)
            $tabla->time('start_time');
            $tabla->time('end_time');
            $tabla->timestamps();

            // Acelera la búsqueda de choques: mismo vehículo + mismo día de la semana.
            $tabla->index(['vehicle_id', 'weekday']);
        });

        // 3) Detalle: un registro por cada fecha en la que se hará el mantenimiento.
        Schema::create('maintenance_days', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('schedule_id')->constrained('maintenance_schedules')->cascadeOnDelete();
            $tabla->date('date');
            $tabla->string('observation', 500)->nullable();
            $tabla->string('image')->nullable();              // ruta pública: /uploads/mantenimientos/<uuid>.jpg
            $tabla->string('status', 20)->default('pendiente'); // pendiente | realizado | no_realizado
            $tabla->timestamps();

            // Un horario no puede tener dos veces la misma fecha.
            $tabla->unique(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_days');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenances');
    }
};
