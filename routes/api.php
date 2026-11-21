<?php

use App\Http\Controllers\Api\Ciudadano\AccesoController as AccesoCiudadano;
use App\Http\Controllers\Api\Ciudadano\ProgramacionController as ProgramacionCiudadano;
use App\Http\Controllers\Api\Ciudadano\RecorridoController as RecorridoCiudadano;
use App\Http\Controllers\Api\Ciudadano\ZonaController as ZonaCiudadano;
use App\Http\Controllers\Api\CiudadanoController;
use App\Http\Controllers\Api\Conductor\AccesoController;
use App\Http\Controllers\Api\Conductor\ProgramacionController;
use App\Http\Controllers\Api\Conductor\RecorridoController;
use App\Http\Controllers\Api\ConsultaRecoleccionController;
use App\Http\Middleware\SoloCiudadanoV1;
use Illuminate\Support\Facades\Route;

/*
| API de la app móvil del conductor (Avance 6). Todas las rutas quedan bajo /api/conductor.
| Autenticación: token de Sanctum en la cabecera «Authorization: Bearer <token>».
*/

Route::get('v1/recoleccion', [ConsultaRecoleccionController::class, 'index'])->middleware('throttle:60,1,recoleccion')->name('api.recoleccion.index');
// El limitador de Laravel usa MySQL en despliegue. El frontal limita esta ruta antes de llegar a PHP.
Route::get('v1/salud', [ConsultaRecoleccionController::class, 'salud'])->name('api.salud');

Route::prefix('v1/ciudadano')->name('api.v1.ciudadano.')->controller(CiudadanoController::class)->group(function () {
    Route::post('registro', 'registrar')->middleware('throttle:6,1,ciudadano-registro')->name('registro');
    Route::post('login', 'login')->middleware('throttle:6,1,ciudadano-login')->name('login');
    Route::get('zonas', 'zonas')->middleware('throttle:60,1,ciudadano-zonas')->name('zonas');
    Route::post('zonas/ubicar', 'ubicar')->middleware('throttle:30,1,ciudadano-ubicar')->name('ubicar');
    Route::middleware(['auth:sanctum', SoloCiudadanoV1::class, 'throttle:120,1,ciudadano-v1-privado'])->group(function () {
        Route::get('perfil', 'perfil')->name('perfil');
        Route::post('logout', 'logout')->name('logout');
        Route::post('zona', 'cambiarZona')->name('zona');
        Route::get('programaciones', 'programaciones')->name('programaciones');
        Route::get('programaciones/{programacion}', 'detalle')->whereNumber('programacion')->name('detalle');
        Route::post('programaciones/{programacion}/valoracion', 'calificar')->whereNumber('programacion')->name('valoracion');
        Route::get('historial', 'historial')->name('historial');
    });
});

Route::prefix('conductor')->name('api.conductor.')->group(function () {
    Route::post('login', [AccesoController::class, 'login'])->middleware('throttle:6,1,conductor-login')->name('login');

    Route::middleware(['auth:sanctum', 'conductor', 'throttle:120,1,conductor-api'])->group(function () {
        Route::get('perfil', [AccesoController::class, 'perfil'])->name('perfil');
        Route::post('logout', [AccesoController::class, 'logout'])->name('logout');

        Route::get('programaciones', [ProgramacionController::class, 'index'])->name('programaciones.index');
        Route::get('programaciones/hoy', [ProgramacionController::class, 'hoy'])->name('programaciones.hoy');
        Route::get('programaciones/{programacion}', [ProgramacionController::class, 'show'])->whereNumber('programacion')->name('programaciones.show');

        Route::post('programaciones/{programacion}/recorrido', [RecorridoController::class, 'iniciar'])->whereNumber('programacion')->name('recorridos.iniciar');
        Route::get('recorridos', [RecorridoController::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/actual', [RecorridoController::class, 'actual'])->name('recorridos.actual');
        Route::get('recorridos/{recorrido}', [RecorridoController::class, 'show'])->whereNumber('recorrido')->name('recorridos.show');
        Route::post('recorridos/{recorrido}/finalizar', [RecorridoController::class, 'finalizar'])->whereNumber('recorrido')->name('recorridos.finalizar');
    });

    // El GPS manda un punto cada 5 s (o un lote si estuvo sin señal): límite propio por minuto.
    Route::middleware(['auth:sanctum', 'conductor', 'throttle:60,1,conductor-gps'])
        ->post('recorridos/{recorrido}/puntos', [RecorridoController::class, 'puntos'])->whereNumber('recorrido')->name('recorridos.puntos');
});

/*
| API de la app móvil del ciudadano (Avance 7). Todas las rutas quedan bajo /api/ciudadano.
| El token del ciudadano (habilidad «ciudadano») no sirve en la API del conductor ni al revés.
*/

Route::prefix('ciudadano')->name('api.ciudadano.')->group(function () {
    Route::post('registro', [AccesoCiudadano::class, 'registro'])->middleware('throttle:10,1,ciudadano-registro-app')->name('registro');
    Route::post('login', [AccesoCiudadano::class, 'login'])->middleware('throttle:6,1,ciudadano-login-app')->name('login');

    // Públicas: se usan al registrarse, cuando todavía no hay sesión.
    Route::middleware('throttle:60,1,ciudadano-zonas-app')->group(function () {
        Route::get('zonas', [ZonaCiudadano::class, 'index'])->name('zonas.index');
        Route::get('zonas/ubicacion', [ZonaCiudadano::class, 'ubicacion'])->name('zonas.ubicacion');
    });

    Route::middleware(['auth:sanctum', 'ciudadano', 'throttle:120,1,ciudadano-api'])->group(function () {
        Route::get('perfil', [AccesoCiudadano::class, 'perfil'])->name('perfil');
        Route::put('perfil/zona', [AccesoCiudadano::class, 'cambiarZona'])->name('perfil.zona');
        Route::post('logout', [AccesoCiudadano::class, 'logout'])->name('logout');

        Route::get('programaciones', [ProgramacionCiudadano::class, 'index'])->name('programaciones.index');
        Route::get('programaciones/{programacion}', [ProgramacionCiudadano::class, 'show'])->whereNumber('programacion')->name('programaciones.show');
        Route::get('programaciones/{programacion}/seguimiento', [ProgramacionCiudadano::class, 'seguimiento'])->whereNumber('programacion')->name('programaciones.seguimiento');

        Route::get('recorridos', [RecorridoCiudadano::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/{recorrido}', [RecorridoCiudadano::class, 'show'])->whereNumber('recorrido')->name('recorridos.show');
        Route::post('recorridos/{recorrido}/calificacion', [RecorridoCiudadano::class, 'calificar'])->whereNumber('recorrido')->name('recorridos.calificar');
    });
});
