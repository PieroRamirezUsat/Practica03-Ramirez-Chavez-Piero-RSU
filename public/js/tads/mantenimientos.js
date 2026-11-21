/**
 * PRÁCTICA 03 – Mantenimiento de vehículos (todo por AJAX con Tads.Gestor, igual que los demás módulos).
 *
 *   - mantenimientos         Lista de mantenimientos
 *   - mantenimientoHorarios  Horarios de un mantenimiento
 *   - mantenimientoDias      Días generados de un horario
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    /* ------------------------------------------------------------------ mantenimientos */

    Tads.Modulos.mantenimientos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'mantenimiento',
                icono: 'bi-tools',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[1, 'asc']],
                buscar: 'Buscar mantenimiento...',
                columnas: [
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'inicio', render: Tads.ordenFecha },
                    { data: 'fin', render: Tads.ordenFecha },
                    { data: 'horarios', className: 'text-center', render: (v) => Tads.insignia(`${v} ${v === 1 ? 'horario' : 'horarios'}`, v ? 'azul' : 'gris') },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            `<a href="${fila.url_horarios}" class="btn btn-sm t-boton py-1 px-2"><i class="bi bi-calendar-week me-1"></i>Ver horarios</a>`,
                            Tads.botonEditar(fila.id),
                            // Eliminación validada: con horarios el botón queda bloqueado (y el servidor también lo rechaza).
                            Tads.botonEliminar(fila.id, fila.nombre, fila.horarios ? 'Tiene horarios: elimínelos primero' : ''),
                        ) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ horarios */

    Tads.Modulos.mantenimientoHorarios = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'horario',
                icono: 'bi-calendar-week',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [],   // se respeta el orden del servidor: por día de la semana y hora
                buscar: 'Buscar horario...',
                columnas: [
                    { data: 'dia' },
                    { data: 'vehiculo', render: (v) => Tads.escapar(v) },
                    { data: 'responsable', render: (v) => Tads.escapar(v) },
                    { data: 'tipo' },
                    { data: 'inicio' },
                    { data: 'fin' },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => `<a href="${fila.url_dias}" class="btn t-boton-icono t-editar" title="Ver días"><i class="bi bi-eye-fill"></i></a>` },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        // b.ii: el mensaje de confirmación avisa que también se borran los días generados.
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, `el horario del ${fila.dia} (${fila.vehiculo}) y sus ${fila.dias} días generados`),
                        ) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ días */

    Tads.Modulos.mantenimientoDias = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'día de mantenimiento',
                icono: 'bi-calendar-check',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                orden: [[0, 'asc']],
                buscar: 'Buscar día...',
                columnas: [
                    { data: 'fecha', render: Tads.ordenFecha },
                    { data: 'observacion', render: (v) => (v ? Tads.escapar(v) : '<span class="text-muted">—</span>') },
                    { data: 'imagen', orderable: false, className: 'text-center',
                        render: (v) => (v ? `<a href="${v}" target="_blank"><img src="${v}" alt="Imagen" style="height:40px;border-radius:6px"></a>` : '—') },
                    { data: 'estado', className: 'text-center', render: (v, modo, fila) => Tads.insignia(v, fila.color) },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => Tads.botonEditar(fila.id) },
                ],
                // Vista previa de la imagen elegida (misma función que usan Marcas y Personal).
                alCargarFormulario: (cuerpo) => Tads.vistaPreviaArchivo(cuerpo.querySelector('#cajaImagenDia'), cuerpo.querySelector('#archivoImagenDia')),
            });
        },
    };
})(window);
