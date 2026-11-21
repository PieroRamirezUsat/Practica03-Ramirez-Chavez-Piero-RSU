{{-- PRÁCTICA 03 – Formulario «Registrar horario». Vehículos y responsables vienen de la BD del proyecto. --}}
<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="horarioVehiculo" class="form-label t-obligatorio">Vehículo</label>
    <select name="vehicle_id" id="horarioVehiculo" class="form-select" required>
        <option value="">Seleccione un vehículo</option>
        @foreach ($vehiculos as $vehiculo)
            <option value="{{ $vehiculo->id }}" @selected(($horario->vehicle_id ?? null) == $vehiculo->id)>{{ $vehiculo->name }} ({{ $vehiculo->plate }})</option>
        @endforeach
    </select>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6">
        <label for="horarioTipo" class="form-label t-obligatorio">Tipo de mantenimiento</label>
        <select name="type" id="horarioTipo" class="form-select" required>
            <option value="">Seleccione</option>
            @foreach ($tipos as $valor => $texto)
                <option value="{{ $valor }}" @selected(($horario->type ?? null) === $valor)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label for="horarioDia" class="form-label t-obligatorio">Día de la semana</label>
        <select name="weekday" id="horarioDia" class="form-select" required>
            <option value="">Seleccione</option>
            @foreach ($dias as $numero => $nombre)
                <option value="{{ $numero }}" @selected(($horario->weekday ?? null) == $numero)>{{ $nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label for="horarioInicio" class="form-label t-obligatorio">Hora de inicio</label>
        <input type="time" name="start_time" id="horarioInicio" class="form-control" required value="{{ $horario->hora_inicio ?? '' }}">
    </div>
    <div class="col-sm-6">
        <label for="horarioFin" class="form-label t-obligatorio">Hora de fin</label>
        <input type="time" name="end_time" id="horarioFin" class="form-control" required value="{{ $horario->hora_fin ?? '' }}">
    </div>
</div>

<div class="mb-3">
    <label for="horarioResponsable" class="form-label t-obligatorio">Responsable</label>
    <select name="responsible_id" id="horarioResponsable" class="form-select" required>
        <option value="">Seleccione un responsable</option>
        @foreach ($responsables as $persona)
            <option value="{{ $persona->id }}" @selected(($horario->responsible_id ?? null) == $persona->id)>{{ $persona->nombre_completo }}</option>
        @endforeach
    </select>
</div>

<div class="t-nota"><i class="bi bi-info-circle-fill text-primary me-1"></i>
    Se generarán los días de las fechas del {{ $mantenimiento->start_date->format('d/m/Y') }} al {{ $mantenimiento->end_date->format('d/m/Y') }}
    que caigan en el día elegido.
    @isset($horario) Si cambia el día de la semana, se borran los días actuales y se generan los nuevos. @endisset
</div>

@include('admin.partials.pie-formulario', ['textoBoton' => 'Guardar horario'])
