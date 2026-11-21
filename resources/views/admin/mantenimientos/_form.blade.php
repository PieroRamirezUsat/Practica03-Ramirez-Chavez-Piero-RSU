{{-- PRÁCTICA 03 – Formulario «Registrar mantenimiento» (se usa en create y edit; llega por AJAX a la ventana emergente). --}}
<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="mantenimientoNombre" class="form-label t-obligatorio">Nombre</label>
    <input type="text" name="name" id="mantenimientoNombre" class="form-control" maxlength="100" required
        placeholder="Ejemplo: MANT. DICIEMBRE 2026" value="{{ old('name', $mantenimiento->name ?? '') }}">
</div>

<div class="row g-3">
    <div class="col-sm-6">
        <label for="mantenimientoInicio" class="form-label t-obligatorio">Fecha de inicio</label>
        <input type="date" name="start_date" id="mantenimientoInicio" class="form-control" required
            value="{{ old('start_date', isset($mantenimiento) ? $mantenimiento->start_date->format('Y-m-d') : '') }}">
    </div>
    <div class="col-sm-6">
        <label for="mantenimientoFin" class="form-label t-obligatorio">Fecha de fin</label>
        <input type="date" name="end_date" id="mantenimientoFin" class="form-control" required
            value="{{ old('end_date', isset($mantenimiento) ? $mantenimiento->end_date->format('Y-m-d') : '') }}">
    </div>
</div>

<div class="t-nota mt-3"><i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong>
    la fecha de fin no puede ser anterior a la de inicio y el rango no puede cruzarse con otro mantenimiento.
</div>

@include('admin.partials.pie-formulario')
