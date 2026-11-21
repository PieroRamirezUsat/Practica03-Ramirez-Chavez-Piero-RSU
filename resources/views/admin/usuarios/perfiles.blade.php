@extends('adminlte::page')

@section('title', 'Perfiles de usuario')

@php
    $niveles = [
        'completo' => ['t-verde', 'Completo'],
        'gestiona' => ['t-azul', 'Gestiona'],
        'ver' => ['t-gris', 'Solo ver'],
        'nada' => [null, '—'],
    ];
@endphp

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-vcard',
        'titulo' => 'Perfiles de usuario',
        'subtitulo' => 'Qué módulos puede usar cada plantilla y quiénes la tienen. Al crear un usuario se elige el perfil; después se ajusta en «Permisos».',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="pf-leyenda">
                <span><span class="t-insignia t-verde">Completo</span> todo el módulo</span>
                <span><span class="t-insignia t-azul">Gestiona</span> puede crear, editar o eliminar</span>
                <span><span class="t-insignia t-gris">Solo ver</span> únicamente consulta</span>
                <span><strong>—</strong> sin acceso</span>
            </div>

            <div class="table-responsive">
                <table class="table t-tabla pf-matriz">
                    <thead>
                        <tr>
                            <th>Perfil</th>
                            @foreach ($modulos as $modulo)
                                <th class="text-center"><i class="bi {{ $modulo['icono'] }} me-1"></i>{{ str_replace('Gestión de ', '', $modulo['titulo']) }}</th>
                            @endforeach
                            <th class="text-center">Permisos</th>
                            <th>Usuarios con este perfil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perfiles as $perfil)
                            <tr id="perfil-{{ $perfil['clave'] }}">
                                <td class="pf-perfil">
                                    <span class="pf-icono t-{{ $perfil['color'] }}"><i class="bi {{ $perfil['icono'] }}"></i></span>
                                    <span>
                                        <strong>{{ $perfil['nombre'] }}</strong>
                                        <small>{{ $perfil['descripcion'] }}</small>
                                    </span>
                                </td>
                                @foreach ($modulos as $modulo)
                                    @php([$clase, $texto] = $niveles[$perfil['niveles'][$modulo['clave']]])
                                    <td class="text-center">
                                        @if ($clase)
                                            <span class="t-insignia {{ $clase }}">{{ $texto }}</span>
                                        @else
                                            <span class="text-muted">{{ $texto }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-center">
                                    @if ($perfil['permisos'] === null)
                                        <span class="t-insignia t-verde">Todos</span>
                                    @elseif ($perfil['clave'] === \App\Support\PerfilesDeUsuario::PERSONALIZADO)
                                        <span class="text-muted small">A medida</span>
                                    @else
                                        <strong>{{ $perfil['total'] }}</strong><span class="text-muted"> / {{ $totalPermisos }}</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($perfil['usuarios']->take(3) as $usuario)
                                        <span class="pf-persona {{ $usuario->status ? '' : 'pf-inactivo' }}" title="{{ $usuario->status ? 'Activo' : 'Inactivo' }}">{{ trim($usuario->name.' '.$usuario->last_name) }}</span>
                                    @empty
                                        <span class="text-muted small">Nadie todavía</span>
                                    @endforelse
                                    @if ($perfil['usuarios']->count() > 3)
                                        <span class="pf-persona pf-mas">+{{ $perfil['usuarios']->count() - 3 }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
