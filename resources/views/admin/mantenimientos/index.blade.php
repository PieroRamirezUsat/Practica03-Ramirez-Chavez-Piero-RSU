{{-- PRÁCTICA 03 – Punto 2.a: lista de mantenimientos programados. La tabla se llena por AJAX (mantenimientos.js). --}}
@extends('adminlte::page')

@section('title', 'Mantenimientos programados')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('admin.inicio') }}"><i class="bi bi-house-door"></i></a></li>
            <li class="breadcrumb-item">Gestión de vehículos</li>
            <li class="breadcrumb-item active" aria-current="page">Mantenimiento</li>
        </ol>
    </nav>

    @include('admin.partials.cabecera', [
        'icono' => 'bi-tools',
        'titulo' => 'Mantenimientos programados',
        'subtitulo' => 'Cada mantenimiento tiene un rango de fechas que no puede cruzarse con otro. Dentro de él se programan los horarios de cada vehículo.',
        'boton' => 'Nuevo mantenimiento',
    ])

    <div class="t-tarjeta"
         id="moduloMantenimientos"
         data-tads-modulo="mantenimientos"
         data-permiso="maintenances"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.mantenimientos.index') }}"
         data-url-recurso="{{ url('admin/mantenimientos') }}"
         data-url-crear="{{ route('admin.mantenimientos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Fecha de inicio</th>
                            <th>Fecha de fin</th>
                            <th class="text-center">Horarios</th>
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
