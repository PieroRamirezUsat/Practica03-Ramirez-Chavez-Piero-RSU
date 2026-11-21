{{-- PRÁCTICA 03 – Formulario «Actualizar día de mantenimiento» (multipart porque lleva la imagen). --}}
<form method="POST" action="{{ route('admin.mantenimientos.dias.update', $dia->id) }}" class="t-formulario" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="t-alerta-form"></div>

    <div class="mb-3">
        <label for="diaFecha" class="form-label">Fecha</label>
        {{-- La fecha la genera el horario: se muestra, pero no se edita. --}}
        <input type="text" id="diaFecha" class="form-control" value="{{ $dia->date->format('d/m/Y') }}" readonly>
    </div>

    <div class="mb-3">
        <label for="diaObservacion" class="form-label">Observación</label>
        <textarea name="observation" id="diaObservacion" class="form-control" rows="3" maxlength="500"
            placeholder="Ejemplo: Todo conforme">{{ old('observation', $dia->observation) }}</textarea>
        <div class="form-text">Máximo 500 caracteres.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Imagen <span class="text-muted fw-normal">(opcional)</span></label>
        <div class="t-caja-foto" id="cajaImagenDia">
            @if ($imagen)
                <div><img src="{{ $imagen }}" alt="Imagen actual"><small>Imagen actual · clic para cambiarla</small></div>
            @else
                <div><i class="bi bi-image"></i><small>Haga clic para elegir una imagen</small></div>
            @endif
        </div>
        <input type="file" name="image" id="archivoImagenDia" class="d-none" accept="image/png,image/jpeg,image/webp">
        <div class="form-text">JPG, PNG o WEBP. Máximo 2 MB.</div>
    </div>

    <div class="form-check form-switch mb-2">
        {{-- Sin marcar, el navegador no envía la casilla: el input oculto manda 0 para que llegue siempre un valor. --}}
        <input type="hidden" name="done" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="done" id="diaRealizado" value="1"
            @checked($dia->status === 'realizado')>
        <label class="form-check-label" for="diaRealizado">Mantenimiento realizado</label>
    </div>
    <div class="form-text mb-2">Al guardar: marcado = «Realizado»; sin marcar = «No realizado».</div>

    @include('admin.partials.pie-formulario', ['textoBoton' => 'Guardar cambios'])
</form>
