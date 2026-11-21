<?php

namespace App\Http\Controllers\Api\Ciudadano;

use App\Http\Controllers\Controller;
use App\Models\Ciudadano;
use App\Services\ServicioCiudadano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registro, ingreso y perfil del ciudadano en la app móvil. */
class AccesoController extends Controller
{
    private const NOMBRE = "regex:/^[\pL\s'.\-]+$/u";

    public function __construct(private ServicioCiudadano $servicio) {}

    public function registro(Request $request): JsonResponse
    {
        $request->merge([
            'dni' => trim((string) $request->input('dni')),
            'name' => trim((string) $request->input('nombre')),
            'last_name' => trim((string) $request->input('apellidos')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $datos = $request->validate([
            'dni' => ['required', 'digits:8', Rule::unique('citizens', 'dni')],
            'name' => ['required', 'string', 'min:2', 'max:100', self::NOMBRE],
            'last_name' => ['required', 'string', 'min:2', 'max:100', self::NOMBRE],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('citizens', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('status', true)],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ], [
            'dni.digits' => 'El DNI tiene 8 dígitos.',
            'dni.unique' => 'Ya hay una cuenta registrada con ese DNI.',
            'name.regex' => 'Los nombres solo pueden tener letras y espacios.',
            'last_name.regex' => 'Los apellidos solo pueden tener letras y espacios.',
            'email.unique' => 'Ya hay una cuenta con ese correo.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'zone_id.exists' => 'La zona elegida no está disponible.',
        ], ['dni' => 'DNI', 'name' => 'nombres', 'last_name' => 'apellidos', 'email' => 'correo', 'password' => 'contraseña', 'zone_id' => 'zona']);

        $ciudadano = Ciudadano::create([
            'dni' => $datos['dni'],
            'name' => $datos['name'],
            'last_name' => $datos['last_name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'zone_id' => $datos['zone_id'],
            'status' => true,
        ]);

        return response()->json($this->sesion($ciudadano, $datos['dispositivo'] ?? null), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ], [], ['email' => 'correo', 'password' => 'contraseña']);

        $ciudadano = Ciudadano::where('email', mb_strtolower(trim($datos['email'])))->first();

        // Mismo mensaje para correo inexistente y contraseña errada: no se revela qué correos existen.
        if (! $ciudadano || ! Hash::check($datos['password'], (string) $ciudadano->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        if (! $ciudadano->status) {
            throw ValidationException::withMessages(['email' => 'Su cuenta está deshabilitada. Consulte con la municipalidad.']);
        }

        return response()->json($this->sesion($ciudadano, $datos['dispositivo'] ?? null));
    }

    public function perfil(Request $request): JsonResponse
    {
        return response()->json(['ciudadano' => $this->servicio->perfil($request->attributes->get('ciudadano'))]);
    }

    /** Cambia la zona del ciudadano (y con ella, lo que ve en la app). */
    public function cambiarZona(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('status', true)],
        ], ['zone_id.exists' => 'La zona elegida no está disponible.'], ['zone_id' => 'zona']);

        $ciudadano = $request->attributes->get('ciudadano');
        $ciudadano->update(['zone_id' => (int) $datos['zone_id']]);

        return response()->json(['ciudadano' => $this->servicio->perfil($ciudadano->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function sesion(Ciudadano $ciudadano, ?string $dispositivo): array
    {
        return [
            'token' => $ciudadano->createToken($dispositivo ?: 'app-ciudadano', ['ciudadano'])->plainTextToken,
            'ciudadano' => $this->servicio->perfil($ciudadano),
            'config' => ['segundos_entre_puntos' => (int) config('proyecto.recorridos.segundos_entre_puntos', 5)],
        ];
    }
}
