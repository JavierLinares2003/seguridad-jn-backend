<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProyectoController;
use App\Http\Controllers\Api\V1\ProyectoContactoController;
use App\Http\Controllers\Api\V1\ProyectoInventarioController;
use App\Http\Controllers\Api\V1\ProyectoConfiguracionPersonalController;
use App\Http\Controllers\Api\V1\OperacionPersonalAsignadoController;
use App\Http\Controllers\Api\V1\OperacionAsistenciaController;
use App\Http\Controllers\Api\V1\TipoDocumentoPersonalController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\PersonalController;
use App\Http\Controllers\Api\V1\PersonalDocumentoController;
use App\Http\Controllers\Api\V1\PrestamoController;
use App\Http\Controllers\Api\V1\TransaccionController;
use App\Http\Controllers\Api\V1\AlertaCoberturaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes (No Authentication Required)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->name('api.v1.auth.login');

        Route::post('/register', function () {
            // TODO: Implement AuthController@register
            return response()->json(['message' => 'Register endpoint']);
        })->name('api.v1.auth.register');

        Route::post('/forgot-password', function () {
            // TODO: Implement AuthController@forgotPassword
            return response()->json(['message' => 'Forgot password endpoint']);
        })->name('api.v1.auth.forgot-password');

        Route::post('/reset-password', function () {
            // TODO: Implement AuthController@resetPassword
            return response()->json(['message' => 'Reset password endpoint']);
        })->name('api.v1.auth.reset-password');
    });

    /*
    |--------------------------------------------------------------------------
    | Catalog Routes (Public - Read Only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('catalogos')->group(function () {
        Route::get('/', [CatalogoController::class, 'catalogos'])
            ->name('api.v1.catalogos.list');

        Route::get('/all', [CatalogoController::class, 'all'])
            ->name('api.v1.catalogos.all');

        Route::get('/{catalogo}', [CatalogoController::class, 'index'])
            ->name('api.v1.catalogos.index');

        Route::get('/{catalogo}/{id}', [CatalogoController::class, 'show'])
            ->name('api.v1.catalogos.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Authentication Required)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum'])->group(function () {

        // Auth endpoints
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])
                ->name('api.v1.auth.me');

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::post('/logout-all', [AuthController::class, 'logoutAll'])
                ->name('api.v1.auth.logout-all');

            Route::post('/refresh', function (Request $request) {
                $request->user()->currentAccessToken()->delete();
                $token = $request->user()->createToken('auth-token')->plainTextToken;
                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'token_type' => 'Bearer',
                    ],
                ]);
            })->name('api.v1.auth.refresh');

            Route::post('/change-password', [AuthController::class, 'changePassword'])
                ->name('api.v1.auth.change-password');
        });

        // User Profile (alias for /auth/me)
        Route::get('/user', function (Request $request) {
            return $request->user()->load('roles', 'permissions');
        })->name('api.v1.user');

        /*
        |--------------------------------------------------------------------------
        | Role-Based Routes
        |--------------------------------------------------------------------------
        */

        // Admin Only Routes
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            Route::get('/dashboard', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Admin dashboard',
                ]);
            })->name('api.v1.admin.dashboard');
        });

        // Routes requiring specific permissions
        Route::middleware(['permission:manage-users'])->prefix('users')->group(function () {
            Route::get('/', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'List users',
                ]);
            })->name('api.v1.users.index');
        });

        /*
        |--------------------------------------------------------------------------
        | Personal Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('personal')->group(function () {
            Route::get('/', [PersonalController::class, 'index'])
                ->name('api.v1.personal.index');

            Route::post('/', [PersonalController::class, 'store'])
                ->name('api.v1.personal.store');

            Route::get('/{personal}', [PersonalController::class, 'show'])
                ->name('api.v1.personal.show');

            Route::get('/{personal}/cv', [PersonalController::class, 'generarCV'])
                ->name('api.v1.personal.cv');

            Route::put('/{personal}', [PersonalController::class, 'update'])
                ->name('api.v1.personal.update');

            Route::delete('/{personal}', [PersonalController::class, 'destroy'])
                ->name('api.v1.personal.destroy');

            Route::post('/{personal}/restore', [PersonalController::class, 'restore'])
                ->name('api.v1.personal.restore');

            Route::patch('/{personal}/estado', [PersonalController::class, 'cambiarEstado'])
                ->name('api.v1.personal.estado');

            // Dirección
            Route::get('/{personal}/direccion', [PersonalController::class, 'getDireccion'])
                ->name('api.v1.personal.direccion.show');

            Route::put('/{personal}/direccion', [PersonalController::class, 'updateDireccion'])
                ->name('api.v1.personal.direccion.update');

            Route::delete('/{personal}/direccion', [PersonalController::class, 'deleteDireccion'])
                ->name('api.v1.personal.direccion.destroy');

            // Foto de Perfil
            Route::post('/{personal}/foto', [PersonalController::class, 'uploadFoto'])
                ->name('api.v1.personal.foto.upload');

            Route::delete('/{personal}/foto', [PersonalController::class, 'deleteFoto'])
                ->name('api.v1.personal.foto.destroy');

            // Referencias Laborales
            Route::get('/{personal}/referencias', [PersonalController::class, 'getReferencias'])
                ->name('api.v1.personal.referencias.index');

            Route::post('/{personal}/referencias', [PersonalController::class, 'storeReferencia'])
                ->name('api.v1.personal.referencias.store');

            Route::get('/{personal}/referencias/{referencia}', [PersonalController::class, 'showReferencia'])
                ->name('api.v1.personal.referencias.show');

            Route::put('/{personal}/referencias/{referencia}', [PersonalController::class, 'updateReferencia'])
                ->name('api.v1.personal.referencias.update');

            Route::delete('/{personal}/referencias/{referencia}', [PersonalController::class, 'deleteReferencia'])
                ->name('api.v1.personal.referencias.destroy');

            // Familiares
            Route::get('/{personal}/familiares', [PersonalController::class, 'getFamiliares'])
                ->name('api.v1.personal.familiares.index');

            Route::post('/{personal}/familiares', [PersonalController::class, 'storeFamiliar'])
                ->name('api.v1.personal.familiares.store');

            Route::put('/{personal}/familiares/{familiar}', [PersonalController::class, 'updateFamiliar'])
                ->name('api.v1.personal.familiares.update');

            Route::delete('/{personal}/familiares/{familiar}', [PersonalController::class, 'deleteFamiliar'])
                ->name('api.v1.personal.familiares.destroy');

            // Redes Sociales
            Route::get('/{personal}/redes-sociales', [PersonalController::class, 'getRedesSociales'])
                ->name('api.v1.personal.redes-sociales.index');

            Route::post('/{personal}/redes-sociales', [PersonalController::class, 'storeRedSocial'])
                ->name('api.v1.personal.redes-sociales.store');

            Route::put('/{personal}/redes-sociales/{redSocial}', [PersonalController::class, 'updateRedSocial'])
                ->name('api.v1.personal.redes-sociales.update');

            Route::delete('/{personal}/redes-sociales/{redSocial}', [PersonalController::class, 'deleteRedSocial'])
                ->name('api.v1.personal.redes-sociales.destroy');

            // Documentos
            Route::get('/{personal}/documentos', [PersonalDocumentoController::class, 'index'])
                ->name('personal.documentos.index');

            Route::post('/{personal}/documentos', [PersonalDocumentoController::class, 'store'])
                ->name('personal.documentos.store');

            Route::get('/{personal}/documentos/resumen', [PersonalDocumentoController::class, 'resumen'])
                ->name('personal.documentos.resumen');

            Route::get('/{personal}/documentos/estado/{estado}', [PersonalDocumentoController::class, 'porEstado'])
                ->name('personal.documentos.porEstado');

            Route::get('/{personal}/documentos/{documento}', [PersonalDocumentoController::class, 'show'])
                ->name('personal.documentos.show');

            Route::get('/{personal}/documentos/{documento}/download', [PersonalDocumentoController::class, 'download'])
                ->name('personal.documentos.download');

            Route::get('/{personal}/documentos/{documento}/preview', [PersonalDocumentoController::class, 'preview'])
                ->name('personal.documentos.preview');

            Route::delete('/{personal}/documentos/{documento}', [PersonalDocumentoController::class, 'destroy'])
                ->name('api.v1.personal.documentos.destroy');

            // Historial de Proyectos
            Route::get('/{personal}/proyectos', [PersonalController::class, 'getHistorialProyectos'])
                ->name('api.v1.personal.proyectos');
        });

        /*
        |--------------------------------------------------------------------------
        | Projects Routes
        |--------------------------------------------------------------------------
        */
        Route::apiResource('proyectos', \App\Http\Controllers\Api\V1\ProyectoController::class)
            ->names('api.v1.proyectos');
        Route::apiResource('proyectos.contactos', \App\Http\Controllers\Api\V1\ProyectoContactoController::class)->scoped();
        Route::apiResource('proyectos.inventario', \App\Http\Controllers\Api\V1\ProyectoInventarioController::class)->scoped();
        Route::apiResource('proyectos.configuracion-personal', \App\Http\Controllers\Api\V1\ProyectoConfiguracionPersonalController::class)->scoped();
        /*
        |--------------------------------------------------------------------------
        | Operations Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('operaciones')->group(function () {
            // Asignaciones CRUD
            Route::get('/asignaciones', [OperacionPersonalAsignadoController::class, 'index'])
                ->name('api.v1.operaciones.asignaciones.index');

            Route::post('/asignar-personal', [OperacionPersonalAsignadoController::class, 'store'])
                ->name('api.v1.operaciones.asignar-personal');

            Route::get('/asignaciones/{id}', [OperacionPersonalAsignadoController::class, 'show'])
                ->name('api.v1.operaciones.asignaciones.show');

            Route::put('/asignaciones/{id}', [OperacionPersonalAsignadoController::class, 'update'])
                ->name('api.v1.operaciones.asignaciones.update');

            Route::delete('/asignaciones/{id}', [OperacionPersonalAsignadoController::class, 'destroy'])
                ->name('api.v1.operaciones.asignaciones.destroy');

            // Acciones sobre asignaciones
            Route::post('/asignaciones/{id}/finalizar', [OperacionPersonalAsignadoController::class, 'finalizar'])
                ->name('api.v1.operaciones.asignaciones.finalizar');

            Route::post('/asignaciones/{id}/suspender', [OperacionPersonalAsignadoController::class, 'suspender'])
                ->name('api.v1.operaciones.asignaciones.suspender');

            Route::post('/asignaciones/{id}/reactivar', [OperacionPersonalAsignadoController::class, 'reactivar'])
                ->name('api.v1.operaciones.asignaciones.reactivar');

            // Personal disponible
            Route::get('/personal-disponible', [OperacionPersonalAsignadoController::class, 'personalDisponible'])
                ->name('api.v1.operaciones.personal-disponible');

            // Calendario de disponibilidad por personal
            Route::get('/personal/{personalId}/calendario', [OperacionPersonalAsignadoController::class, 'calendario'])
                ->name('api.v1.operaciones.personal.calendario');

            // Estadísticas por proyecto
            Route::get('/proyectos/{proyectoId}/estadisticas', [OperacionPersonalAsignadoController::class, 'estadisticas'])
                ->name('api.v1.operaciones.proyectos.estadisticas');

            // ========================================
            // Asistencia
            // ========================================

            // CRUD Asistencia
            Route::get('/asistencia', [OperacionAsistenciaController::class, 'index'])
                ->name('api.v1.operaciones.asistencia.index');

            Route::post('/asistencia', [OperacionAsistenciaController::class, 'store'])
                ->name('api.v1.operaciones.asistencia.store');

            Route::get('/asistencia/{id}', [OperacionAsistenciaController::class, 'show'])
                ->name('api.v1.operaciones.asistencia.show');

            Route::put('/asistencia/{id}', [OperacionAsistenciaController::class, 'update'])
                ->name('api.v1.operaciones.asistencia.update');

            Route::delete('/asistencia/{id}', [OperacionAsistenciaController::class, 'destroy'])
                ->name('api.v1.operaciones.asistencia.destroy');

            // Consultas de asistencia
            Route::get('/asistencia/fecha/{fecha}', [OperacionAsistenciaController::class, 'porFecha'])
                ->name('api.v1.operaciones.asistencia.por-fecha');

            Route::get('/asistencia/proyecto/{proyectoId}', [OperacionAsistenciaController::class, 'porProyecto'])
                ->name('api.v1.operaciones.asistencia.por-proyecto');

            Route::get('/asistencia/resumen/{proyectoId}', [OperacionAsistenciaController::class, 'resumen'])
                ->name('api.v1.operaciones.asistencia.resumen');

            Route::get('/asistencia/historial/{personalId}', [OperacionAsistenciaController::class, 'historialPersonal'])
                ->name('api.v1.operaciones.asistencia.historial');

            // Acciones de asistencia
            Route::post('/asistencia/{id}/entrada', [OperacionAsistenciaController::class, 'marcarEntrada'])
                ->name('api.v1.operaciones.asistencia.entrada');

            Route::post('/asistencia/{id}/salida', [OperacionAsistenciaController::class, 'marcarSalida'])
                ->name('api.v1.operaciones.asistencia.salida');

            // Descansos automáticos
            Route::post('/asistencia/generar-descansos', [OperacionAsistenciaController::class, 'generarDescansos'])
                ->name('api.v1.operaciones.asistencia.generar-descansos');

            // Reemplazos disponibles
            Route::get('/asistencia/reemplazos-disponibles', [OperacionAsistenciaController::class, 'reemplazosDisponibles'])
                ->name('api.v1.operaciones.asistencia.reemplazos-disponibles');

            // Préstamos
            Route::get('/prestamos', [PrestamoController::class, 'index'])
                ->name('api.v1.operaciones.prestamos.index');
            Route::post('/prestamos', [PrestamoController::class, 'store'])
                ->name('api.v1.operaciones.prestamos.store');
            Route::get('/prestamos/{id}', [PrestamoController::class, 'show'])
                ->name('api.v1.operaciones.prestamos.show');
            Route::post('/prestamos/{id}/cancelar', [PrestamoController::class, 'cancelar'])
                ->name('api.v1.operaciones.prestamos.cancelar');
            Route::get('/prestamos/{id}/historial', [PrestamoController::class, 'historial'])
                ->name('api.v1.operaciones.prestamos.historial');

            // Transacciones
            Route::get('/transacciones', [TransaccionController::class, 'index'])
                ->name('api.v1.operaciones.transacciones.index');
            Route::post('/transacciones', [TransaccionController::class, 'store'])
                ->name('api.v1.operaciones.transacciones.store');
            Route::get('/transacciones/{id}', [TransaccionController::class, 'show'])
                ->name('api.v1.operaciones.transacciones.show');
            Route::post('/transacciones/{id}/cancelar', [TransaccionController::class, 'cancelar'])
                ->name('api.v1.operaciones.transacciones.cancelar');
            Route::post('/transacciones/{id}/aplicar', [TransaccionController::class, 'aplicar'])
                ->name('api.v1.operaciones.transacciones.aplicar');

            // Alertas de cobertura
            Route::get('/alertas-cobertura', [AlertaCoberturaController::class, 'index'])
                ->name('api.v1.operaciones.alertas-cobertura');

            // Planillas
            Route::prefix('planillas')->group(function () {
                Route::post('/generar', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'generar'])
                    ->name('api.v1.operaciones.planillas.generar');
                Route::get('/', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'index'])
                    ->name('api.v1.operaciones.planillas.index');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'show'])
                    ->name('api.v1.operaciones.planillas.show');
                Route::put('/{id}/aprobar', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'aprobar'])
                    ->name('api.v1.operaciones.planillas.aprobar');
                Route::put('/{id}/marcar-pagada', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'marcarPagada'])
                    ->name('api.v1.operaciones.planillas.marcar-pagada');
                Route::put('/{id}/cancelar', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'cancelar'])
                    ->name('api.v1.operaciones.planillas.cancelar');
                Route::get('/{id}/export/{formato}', [\App\Http\Controllers\Api\V1\PlanillaController::class, 'export'])
                    ->name('api.v1.operaciones.planillas.export');
            });
        });


        // Proyecto Documentos
        Route::prefix('proyectos/{proyecto}/documentos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'index'])
                ->name('proyecto.documentos.index');

            Route::post('/', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'store'])
                ->name('proyecto.documentos.store');

            Route::get('/resumen', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'resumen'])
                ->name('proyecto.documentos.resumen');

            Route::get('/estado/{estado}', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'porEstado'])
                ->name('proyecto.documentos.porEstado');

            Route::get('/{documento}', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'show'])
                ->name('proyecto.documentos.show');

            Route::get('/{documento}/download', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'download'])
                ->name('proyecto.documentos.download');

            Route::get('/{documento}/preview', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'preview'])
                ->name('proyecto.documentos.preview');

            Route::delete('/{documento}', [\App\Http\Controllers\Api\V1\ProyectoDocumentoController::class, 'destroy'])
                ->name('api.v1.proyecto.documentos.destroy');
        });

        // Proyecto Actas
        Route::prefix('proyectos/{proyecto}/actas')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ProyectoActaController::class, 'index'])
                ->name('api.v1.proyecto.actas.index');
            Route::post('/', [\App\Http\Controllers\Api\V1\ProyectoActaController::class, 'store'])
                ->name('api.v1.proyecto.actas.store');
            Route::get('/{id}/download', [\App\Http\Controllers\Api\V1\ProyectoActaController::class, 'download'])
                ->name('api.v1.proyecto.actas.download');
        });

    });

});

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
})->name('api.health');
