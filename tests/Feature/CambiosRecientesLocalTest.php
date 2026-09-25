<?php

use App\Http\Controllers\Api\V1\BodegaArmaController;
use App\Models\OperacionAsistencia;
use App\Models\Personal;
use App\Services\Operacion\AsistenciaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corre contra la base local pgsql dentro de una transacción.
 * phpunit.xml fuerza sqlite, así que cada test reconecta a seguridad_jn
 * y revierte. Un finally borra filas de prueba si el rollback no alcanzó.
 */
function usarPgsqlLocal(): void
{
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => '127.0.0.1',
        'database.connections.pgsql.port' => '5432',
        'database.connections.pgsql.database' => 'seguridad_jn',
        'database.connections.pgsql.username' => 'seguridad_jn',
    ]);

    DB::purge('pgsql');
    DB::reconnect('pgsql');
}

function personalDePrueba(array $extra = []): Personal
{
    $n = random_int(1000, 999999);

    return Personal::create(array_merge([
        'nombres' => 'TESTCUBRIDOR',
        'apellidos' => 'PRUEBA LOCAL',
        'dpi' => '99'.str_pad((string) ($n % 100000000000), 11, '0', STR_PAD_LEFT),
        'email' => "test.cubridor.{$n}@example.test",
        'telefono' => '55550000',
        'fecha_nacimiento' => '1990-01-01',
        'altura' => 1.70,
        'peso' => 150,
        'salario_base' => 0,
        'puesto' => 'TEST',
        'estado' => 'activo',
        'es_administrativo' => false,
    ], $extra));
}

function limpiarPersonalDePrueba(array $ids): void
{
    if ($ids === []) {
        return;
    }

    OperacionAsistencia::query()->whereIn('personal_id', $ids)->delete();
    Personal::withTrashed()->whereIn('id', $ids)->where('nombres', 'TESTCUBRIDOR')->forceDelete();
}

beforeEach(function () {
    usarPgsqlLocal();
    DB::beginTransaction();
});

afterEach(function () {
    try {
        DB::rollBack();
    } catch (Throwable $e) {
        // La conexión puede no tener transacción si el test falló antes.
    }
});

it('acepta status_proceso valido y rechaza uno invalido', function () {
    $controller = app(BodegaArmaController::class);
    $metodo = new ReflectionMethod($controller, 'validar');

    $serie = 'TEST-STATUS-'.uniqid();

    $valido = $metodo->invoke($controller, Request::create('/armas', 'POST', [
        'tipo' => '9mm',
        'serie' => $serie,
        'status_proceso' => 'en_proceso',
        'estado' => 'en_bodega',
    ]));

    expect($valido['status_proceso'])->toBe('en_proceso');

    $vacio = $metodo->invoke($controller, Request::create('/armas', 'POST', [
        'tipo' => 'revolver',
        'serie' => $serie.'-2',
        'estado' => 'en_bodega',
    ]));

    expect($vacio['status_proceso'])->toBe('sin_proceso');

    expect(fn () => $metodo->invoke($controller, Request::create('/armas', 'POST', [
        'tipo' => 'escopeta',
        'serie' => $serie.'-3',
        'status_proceso' => 'en_tramite',
        'estado' => 'en_bodega',
    ])))->toThrow(ValidationException::class);
});

it('incluye como cubridor a quien tiene asistencia directa que no es cobertura y excluye a quien ya cubre', function () {
    $ids = [];

    try {
        $disponible = personalDePrueba();
        $yaCubre = personalDePrueba(['apellidos' => 'PRUEBA YA CUBRE']);
        $ids = [$disponible->id, $yaCubre->id];

        $fecha = Carbon::parse('2099-06-15');

        OperacionAsistencia::create([
            'personal_id' => $disponible->id,
            'personal_asignado_id' => null,
            'fecha_asistencia' => $fecha->toDateString(),
            'es_cobertura' => false,
            'es_descanso' => false,
            'es_ausente' => false,
            'hora_entrada' => '08:00',
        ]);

        OperacionAsistencia::create([
            'personal_id' => $yaCubre->id,
            'personal_asignado_id' => null,
            'fecha_asistencia' => $fecha->toDateString(),
            'es_cobertura' => true,
            'es_descanso' => false,
            'es_ausente' => false,
            'hora_entrada' => '08:00',
        ]);

        $resultado = app(AsistenciaService::class)
            ->getPersonalDisponibleParaReemplazo($fecha)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        expect($resultado->contains((int) $disponible->id))->toBeTrue();
        expect($resultado->contains((int) $yaCubre->id))->toBeFalse();
    } finally {
        try {
            DB::rollBack();
        } catch (Throwable $e) {
        }
        DB::beginTransaction();
        limpiarPersonalDePrueba($ids);
        DB::rollBack();
    }
});

it('calcula minutos tarde y salida temprana con el horario administrativo', function () {
    $ids = [];

    try {
        $admin = personalDePrueba([
            'es_administrativo' => true,
            'horario_entrada' => '08:00',
            'horario_salida' => '17:00',
            'apellidos' => 'PRUEBA HORARIO',
        ]);
        $ids = [$admin->id];

        $tarde = OperacionAsistencia::create([
            'personal_id' => $admin->id,
            'personal_asignado_id' => null,
            'fecha_asistencia' => '2099-06-16',
            'es_cobertura' => false,
            'es_descanso' => false,
            'es_ausente' => false,
            'hora_entrada' => '08:20',
            'hora_salida' => '16:40',
        ])->fresh();

        expect($tarde->llego_tarde)->toBeTrue();
        expect((int) $tarde->minutos_retraso)->toBe(20);
        expect((int) $tarde->minutos_salida_temprana)->toBe(20);

        $dentro = OperacionAsistencia::create([
            'personal_id' => $admin->id,
            'personal_asignado_id' => null,
            'fecha_asistencia' => '2099-06-17',
            'es_cobertura' => false,
            'es_descanso' => false,
            'es_ausente' => false,
            'hora_entrada' => '08:05',
            'hora_salida' => '16:55',
        ])->fresh();

        expect($dentro->llego_tarde)->toBeFalse();
        expect((int) $dentro->minutos_retraso)->toBe(0);
        expect((int) $dentro->minutos_salida_temprana)->toBe(0);
    } finally {
        try {
            DB::rollBack();
        } catch (Throwable $e) {
        }
        DB::beginTransaction();
        limpiarPersonalDePrueba($ids);
        DB::rollBack();
    }
});
