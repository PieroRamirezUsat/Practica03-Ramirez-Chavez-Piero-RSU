{{-- PRÁCTICA 03 – Punto 2.c: días generados de UN horario. La tabla se llena por AJAX (mantenimientos.js). --}}
@extends('adminlte::page')

@section('title', $horario->mantenimiento->name.' – Días programados')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('admin.inicio') }}"><i class="bi bi-house-door"></i></a></li>
            <li class="breadcrumb-item">Gestión de vehículos</li>
            <li class="breadcrumb-item"><a href="{{ route('admin.mantenimientos.index') }}">Mantenimiento</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.mantenimientos.horarios.index', $horario->maintenance_id) }}">{{ $horario->mantenimiento->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $horario->nombre_dia }} / {{ $horario->vehiculo?->name }}</li>
        </ol>
    </nav>

    @include('admin.partials.cabecera', [
        'icono' => 'bi-calendar-check',
        'titulo' => $horario->mantenimiento->name.' – Días programados',
        'subtitulo' => $horario->nombre_dia.' de '.$horario->hora_inicio.' a '.$horario->hora_fin.' · '.$horario->vehiculo?->name
            .' · '.$horario->nombre_tipo.' · Responsable: '.$horario->responsable?->nombre_completo,
    ])

    <div class="t-tarjeta"
         id="moduloDiasMantenimiento"
         data-tads-modulo="mantenimientoDias"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.dias.index', $horario->id) }}"
         data-url-recurso="{{ url('admin/dias-mantenimiento') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="mb-3">
                <a href="{{ route('admin.mantenimientos.horarios.index', $horario->maintenance_id) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver a horarios
                </a>
            </div>
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Observación</th>
                            <th class="text-center">Imagen</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Editar</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
