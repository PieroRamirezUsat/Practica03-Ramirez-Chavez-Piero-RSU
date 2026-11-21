<?php

namespace App\Support;

/**
 * Plantillas (perfiles) predefinidas de usuario del sistema. Cada una trae los permisos que le corresponden a su puesto,
 * de modo que al crear un usuario basta con elegir el perfil y ya ve solo las vistas de su trabajo.
 *
 * Los permisos de la plantilla se COPIAN al usuario al asignarle el perfil: después se pueden ajustar uno por uno en
 * «Permisos». El perfil «operador» (personalizado) no trae permisos: se arman a medida. El administrador tiene acceso total.
 */
final class PerfilesDeUsuario
{
    public const ADMINISTRADOR = 'administrador';

    public const PERSONALIZADO = 'operador';

    /** Los cuatro permisos de un recurso (ver, crear, editar, eliminar) más los que se indiquen. */
    private static function crud(string $prefijo, string ...$extras): array
    {
        return [...array_map(fn (string $accion) => "{$prefijo}.{$accion}", ['view', 'create', 'edit', 'delete']), ...$extras];
    }

    /** @return list<string> todos los permisos de «ver» (las vistas de consulta) */
    private static function verTodo(): array
    {
        return array_values(array_filter(CatalogoPermisos::codigos(), fn (string $c) => str_ends_with($c, '.view') && $c !== 'users.view'));
    }

    /** @return array<string, array{nombre: string, icono: string, color: string, descripcion: string, permisos: list<string>|null}> */
    public static function todos(): array
    {
        static $perfiles = null;

        return $perfiles ??= [
            self::ADMINISTRADOR => [
                'nombre' => 'Administrador', 'icono' => 'bi-shield-fill-check', 'color' => 'verde', 'permisos' => null,
                'descripcion' => 'Acceso total al sistema, incluida la gestión de usuarios y permisos.',
            ],
            'jefe_operaciones' => [
                'nombre' => 'Jefe de operaciones', 'icono' => 'bi-diagram-3-fill', 'color' => 'azul',
                'descripcion' => 'Dirige la recolección: programa, cambia y supervisa las rutas, y consulta personal, flota y asistencia.',
                'permisos' => [
                    ...self::crud('shifts'), ...self::crud('zones', 'zones.map-view'), ...self::crud('holidays'), ...self::crud('employeegroups'),
                    ...self::crud('scheduling'), 'dashboard.view', ...self::crud('reasons'), ...self::crud('scheduling-changes'),
                    'employees.view', 'contracts.view', 'vacations.view', 'vacations.edit', 'attendances.view', 'employeetypes.view',
                    'vehicles.view', 'colors.view', 'brands.view', 'brandmodels.view', 'vehicletypes.view',
                ],
            ],
            'planificador' => [
                'nombre' => 'Planificador de rutas', 'icono' => 'bi-calendar3', 'color' => 'turquesa',
                'descripcion' => 'Arma turnos, zonas, feriados, grupos y programaciones, y atiende los cambios del día.',
                'permisos' => [
                    ...self::crud('shifts'), ...self::crud('zones', 'zones.map-view'), ...self::crud('holidays'), ...self::crud('employeegroups'),
                    ...self::crud('scheduling'), 'dashboard.view', ...self::crud('reasons'), ...self::crud('scheduling-changes'),
                    'employees.view', 'vehicles.view',
                ],
            ],
            'encargado_flota' => [
                'nombre' => 'Encargado de flota', 'icono' => 'bi-truck', 'color' => 'ambar',
                'descripcion' => 'Administra los vehículos: colores, marcas, modelos, tipos, vehículos e imágenes.',
                'permisos' => [
                    ...self::crud('colors'), ...self::crud('brands'), ...self::crud('brandmodels'), ...self::crud('vehicletypes'),
                    ...self::crud('vehicles', 'vehicles.manage-images'), ...self::crud('maintenances'), 'scheduling.view', 'dashboard.view',
                ],
            ],
            'encargado_personal' => [
                'nombre' => 'Encargado de personal', 'icono' => 'bi-people-fill', 'color' => 'violeta',
                'descripcion' => 'Recursos humanos: tipos de personal, personal, contratos, vacaciones y asistencias.',
                'permisos' => [
                    ...self::crud('employeetypes'), ...self::crud('employees'), ...self::crud('contracts'), ...self::crud('vacations'),
                    ...self::crud('attendances'), 'employeegroups.view', 'scheduling.view',
                ],
            ],
            'asistencia' => [
                'nombre' => 'Control de asistencia', 'icono' => 'bi-fingerprint', 'color' => 'rojo',
                'descripcion' => 'Registra y corrige las asistencias y consulta al personal y la programación del día.',
                'permisos' => ['attendances.view', 'attendances.create', 'attendances.edit', 'employees.view', 'scheduling.view', 'dashboard.view'],
            ],
            'consulta' => [
                'nombre' => 'Solo consulta', 'icono' => 'bi-eye-fill', 'color' => 'gris',
                'descripcion' => 'Puede ver todas las listas y el dashboard, pero no crear, editar ni eliminar nada.',
                'permisos' => self::verTodo(),
            ],
            self::PERSONALIZADO => [
                'nombre' => 'Personalizado', 'icono' => 'bi-sliders', 'color' => 'gris',
                'descripcion' => 'Sin permisos de partida: se arman uno por uno en «Permisos».',
                'permisos' => [],
            ],
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    public static function existe(?string $clave): bool
    {
        return $clave !== null && array_key_exists($clave, self::todos());
    }

    public static function nombre(?string $clave): string
    {
        return self::todos()[$clave]['nombre'] ?? 'Personalizado';
    }

    public static function color(?string $clave): string
    {
        return self::todos()[$clave]['color'] ?? 'gris';
    }

    /** Permisos que trae la plantilla (el administrador tiene todos). @return list<string> */
    public static function permisos(?string $clave): array
    {
        $perfil = self::todos()[$clave] ?? null;

        if ($perfil === null) {
            return [];
        }

        return $perfil['permisos'] === null ? CatalogoPermisos::codigos() : array_values(array_unique($perfil['permisos']));
    }

    /** ¿La plantilla trae permisos fijos (no es el administrador ni el personalizado)? */
    public static function traePermisos(?string $clave): bool
    {
        $perfil = self::todos()[$clave] ?? null;

        return $perfil !== null && $perfil['permisos'] !== null && $perfil['permisos'] !== [];
    }

    /**
     * Nivel de acceso del perfil en cada módulo: «completo» (todos los permisos del módulo), «gestiona» (puede crear, editar o eliminar),
     * «ver» (solo consulta) o «nada».
     *
     * @return array<string, string> clave del módulo => nivel
     */
    public static function nivelesPorModulo(?string $clave): array
    {
        $tiene = array_flip(self::permisos($clave));
        $niveles = [];

        foreach (CatalogoPermisos::modulos() as $modulo) {
            $codigos = collect($modulo['recursos'])->flatMap(fn ($r) => collect($r['permisos'])->pluck('codigo'))->all();
            $propios = array_values(array_filter($codigos, fn (string $c) => isset($tiene[$c])));

            $niveles[$modulo['clave']] = match (true) {
                $propios === [] => 'nada',
                count($propios) === count($codigos) => 'completo',
                count(array_filter($propios, fn (string $c) => ! str_ends_with($c, '.view'))) > 0 => 'gestiona',
                default => 'ver',
            };
        }

        return $niveles;
    }

    /**
     * Las vistas que le corresponden a un perfil, por módulo: recurso y acciones que puede hacer.
     *
     * @return list<array{titulo: string, icono: string, recursos: list<array{titulo: string, acciones: list<string>}>}>
     */
    public static function vistas(?string $clave): array
    {
        $tiene = array_flip(self::permisos($clave));
        $modulos = [];

        foreach (CatalogoPermisos::modulos() as $modulo) {
            $recursos = [];

            foreach ($modulo['recursos'] as $recurso) {
                $acciones = array_values(array_unique(array_map(
                    fn (array $p) => $p['tipo'],
                    array_filter($recurso['permisos'], fn (array $p) => isset($tiene[$p['codigo']])),
                )));

                if ($acciones !== []) {
                    $recursos[] = ['titulo' => $recurso['titulo'], 'acciones' => $acciones];
                }
            }

            if ($recursos !== []) {
                $modulos[] = ['titulo' => $modulo['titulo'], 'icono' => $modulo['icono'], 'recursos' => $recursos];
            }
        }

        return $modulos;
    }
}
