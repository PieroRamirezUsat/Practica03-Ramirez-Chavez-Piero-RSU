{{-- PRÁCTICA 03 – Punto 2.b: horarios de UN mantenimiento. La tabla se llena por AJAX (mantenimientos.js). --}}
@extends('adminlte::page')

@section('title', $mantenimiento->name.' – Horarios')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('admin.inicio') }}"><i class="bi bi-house-door"></i></a></li>
            <li class="breadcrumb-item">Gestión de vehículos</li>
            <li class="breadcrumb-item"><a href="{{ route('admin.mantenimientos.index') }}">Mantenimiento</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $mantenimiento->name }}</li>
        </ol>
    </nav>

    @include('admin.partials.cabecera', [
        'icono' => 'bi-calendar-week',
        'titulo' => $mantenimiento->name.' / Horarios',
        'subtitulo' => 'Del '.$mantenimiento->start_date->format('d/m/Y').' al '.$mantenimiento->end_date->format('d/m/Y')
            .'. Al guardar un horario se generan sus días; un vehículo no puede tener dos horarios que se crucen el mismo día.',
        'boton' => 'Agregar horario',
    ])

    <div class="t-tarjeta"
         id="moduloHorariosMantenimiento"
         data-tads-modulo="mantenimientoHorarios"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.horarios.index', $mantenimiento->id) }}"
         data-url-recurso="{{ url('admin/horarios-mantenimiento') }}"
         data-url-crear="{{ route('admin.mantenimientos.horarios.create', $mantenimiento->id) }}">
        <div class="t-tarjeta-cuerpo">
            <div class="mb-3">
                <a href="{{ route('admin.mantenimientos.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver a mantenimientos
                </a>
            </div>
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Vehículo</th>
                            <th>Responsable</th>
                            <th>Tipo</th>
                            <th>Hora de inicio</th>
                            <th>Hora de fin</th>
                            <th class="text-center">Ver días</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
