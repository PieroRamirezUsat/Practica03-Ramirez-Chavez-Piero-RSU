<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PerfilesDeUsuario;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo del Avance 05 (solo desarrollo): un usuario por cada perfil predefinido, para probar qué vistas ve cada plantilla.
 * Todos entran con su correo y la clave «Perfil12345»; el operador personalizado, con operador@rsu.test / Operador123.
 */
class UsuarioOperadorDemoSeeder extends Seeder
{
    /** perfil => [correo, nombre, apellidos, DNI] */
    private const USUARIOS = [
        'jefe_operaciones' => ['jefe@rsu.test', 'Julia', 'Jefa de Operaciones', '42311350'],
        'planificador' => ['planificador@rsu.test', 'Pedro', 'Planificador', '42311351'],
        'encargado_flota' => ['flota@rsu.test', 'Fabiola', 'Encargada de Flota', '42311352'],
        'encargado_personal' => ['personal@rsu.test', 'Raúl', 'Encargado de Personal', '42311353'],
        'asistencia' => ['asistencia@rsu.test', 'Ana', 'Control de Asistencia', '42311354'],
        'consulta' => ['consulta@rsu.test', 'Carlos', 'Consulta', '42311355'],
    ];

    public function run(): void
    {
        // Operador personalizado (permisos armados a mano): solo consulta de vehículos y programación.
        $operador = $this->usuario('operador@rsu.test', 'Miguel', 'Operador Demo', '42311349', PerfilesDeUsuario::PERSONALIZADO, 'Operador123');
        $operador->permisos()->delete();
        $operador->permisos()->createMany(array_map(
            fn (string $codigo) => ['permission' => $codigo],
            ['colors.view', 'brands.view', 'brandmodels.view', 'vehicletypes.view', 'vehicles.view', 'scheduling.view', 'dashboard.view'],
        ));

        foreach (self::USUARIOS as $perfil => [$correo, $nombre, $apellidos, $dni]) {
            $usuario = $this->usuario($correo, $nombre, $apellidos, $dni, $perfil, 'Perfil12345');
            $usuario->permisos()->delete();
            $usuario->permisos()->createMany(array_map(fn (string $codigo) => ['permission' => $codigo], PerfilesDeUsuario::permisos($perfil)));
        }
    }

    private function usuario(string $correo, string $nombre, string $apellidos, string $dni, string $perfil, string $clave): User
    {
        $usuario = User::updateOrCreate(['email' => $correo], ['name' => $nombre, 'password' => $clave, 'email_verified_at' => now()]);

        $usuario->forceFill([
            'last_name' => $apellidos, 'dni' => $dni, 'birthdate' => '1990-06-15', 'phone' => '987654321',
            'address' => 'Av. Principal 123, José Leonardo Ortiz', 'role' => $perfil, 'status' => true,
        ])->save();

        return $usuario;
    }
}
