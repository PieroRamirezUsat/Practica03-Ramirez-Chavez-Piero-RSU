# Práctica 03 – Mantenimiento de vehículos

Menú: **Gestión de vehículos → Mantenimiento** (`/admin/mantenimientos`). Todo por **AJAX**.
Se reutilizó el patrón de los demás módulos: controlador con `RespondeAjax` (como `MotivoController`), vistas con los
parciales `cabecera` / `ventana-formulario` / `pie-formulario`, y la tabla + formularios con `Tads.Gestor` (como `cambios.js`).

```
Mantenimiento (nombre, inicio, fin)
  └─ Horario (vehículo, tipo, día de la semana, hora inicio/fin, responsable)
       └─ Días generados (fecha, observación, imagen, estado)
```

## Archivos

| Capa | Archivo |
|---|---|
| Tablas | `database/migrations/2026_11_21_100000_create_maintenance_tables.php` |
| Modelos | `app/Models/Mantenimiento.php`, `HorarioMantenimiento.php`, `DiaMantenimiento.php` |
| Controladores | `app/Http/Controllers/Admin/MantenimientoController.php` (2.a), `HorarioMantenimientoController.php` (2.b), `DiaMantenimientoController.php` (2.c) |
| Vistas | `resources/views/admin/mantenimientos/` (+ `horarios/` y `dias/`) |
| JS | `public/js/tads/mantenimientos.js` |
| Menú / rutas / permisos | `config/adminlte.php`, `routes/web.php`, `app/Support/CatalogoPermisos.php` |
| Pruebas | `tests/Feature/MantenimientoTest.php` |

## Reglas

| Requisito | Regla | Dónde |
|---|---|---|
| 2.a.ii | fin ≥ inicio (`after_or_equal:start_date`) | `MantenimientoController::validado` |
| 2.a.ii | No solapar: `inicio <= otroFin` y `fin >= otroInicio` | `Mantenimiento::scopeQueSeSolapanCon` |
| 2.a.i | Eliminar: confirmación + no se permite si tiene horarios | `MantenimientoController::destroy` |
| 2.b.i | Días = fechas del rango que caen en el día elegido (`CarbonPeriod`) | `HorarioMantenimientoController::fechasDelDia` / `generarDias` |
| 2.b.ii | Eliminar horario borra sus días e imágenes; la confirmación dice cuántos | `HorarioMantenimientoController::destroy` |
| 2.b.iii | Mismo vehículo + mismo día + `inicio < otroFin` y `fin > otroInicio` | `HorarioMantenimiento::scopeQueChocanCon` |
| 2.c | Observación (máx. 500), imagen JPG/PNG/WEBP ≤ 2 MB, realizado sí/no | `DiaMantenimientoController::update` |

Extras (restricciones de lógica que el PDF no pide pero el sistema necesita):

- Si el mantenimiento ya tiene horarios, no se le cambian las fechas.
- El **responsable** tampoco puede tener dos mantenimientos que se crucen (misma regla de horas que el vehículo).
- Si al editar un horario cambia el día, sus días se regeneran; no se permite si alguno ya tiene resultado registrado.
- No se puede eliminar un **vehículo** ni una **persona** que figuren en un mantenimiento (antes daba error 500);
  ver `VehiculoController::destroy` y `PersonalController::destroy`.

## Ojo con el ejemplo del PDF

El **01/12/2026 es martes**: los lunes de diciembre 2026 son **07, 14, 21 y 28**. Las fechas 01, 08, 15, 22 y 29 del
enunciado son los martes (o los lunes de diciembre 2025).

## Probar

`php artisan test --filter=MantenimientoTest`
