<?php

namespace Database\Seeders;

use App\Models\BodegaEntrega;
use App\Models\BodegaKit;
use App\Models\BodegaProducto;
use App\Models\BodegaProveedor;
use App\Models\Personal;
use App\Services\BodegaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BodegaKitsYLiquidacionSeeder extends Seeder
{
    public function run(): void
    {
        $bodega = app(BodegaService::class);

        $this->limpiarProveedores($bodega);
        $this->crearKits();
        $this->casoLiquidacion($bodega);
    }

    private function limpiarProveedores(BodegaService $bodega): void
    {
        $oficiales = [
            'Industrial Magaña',
            'Gran Ahorro',
            'Sergio Ruano',
            'Distribuidora Julissa',
            'Ismael',
            'Librería Progreso',
            'Saida',
            'José Recinos',
            'Distribuidora Jerusalem',
            'MB Printing',
            'Norma',
            'Guillermo Palacios',
            'GEB',
            'Alejandro',
            'Mario Grijalva',
            'Kevin Roberto Elieser Cumes',
            'Francisco Javier',
        ];

        $aliases = [
            'gran ahorro' => 'Gran Ahorro',
            'guillermo palacios' => 'Guillermo Palacios',
            'saida lopez' => 'Saida',
            'sergio uniforme' => 'Sergio Ruano',
            'ismae' => 'Ismael',
            'don ismael galeon y corp' => 'Ismael',
        ];

        foreach (BodegaProveedor::orderBy('id')->get() as $prov) {
            $key = mb_strtolower(trim((string) $prov->nombre));
            $canonico = $aliases[$key] ?? null;
            if (!$canonico) {
                foreach ($aliases as $alias => $nombre) {
                    if (str_starts_with($key, $alias)) {
                        $canonico = $nombre;
                        break;
                    }
                }
            }
            if ($canonico && $prov->nombre !== $canonico) {
                $keeper = BodegaProveedor::where('nombre', $canonico)->first();
                if ($keeper && $keeper->id !== $prov->id) {
                    DB::table('bodega_movimientos')->where('proveedor_id', $prov->id)->update(['proveedor_id' => $keeper->id]);
                    $prov->update(['activo' => false, 'observaciones' => trim(($prov->observaciones ? $prov->observaciones . ' | ' : '') . 'Duplicado de ' . $keeper->nombre)]);
                    continue;
                }
                $prov->update(['nombre' => $canonico]);
            }
        }

        $grupos = BodegaProveedor::all()->groupBy(fn ($p) => mb_strtolower(trim((string) $p->nombre)));
        foreach ($grupos as $lista) {
            if ($lista->count() < 2) {
                continue;
            }
            $keeper = $lista->sortByDesc(function ($p) {
                return ($p->insumo ? 10 : 0) + ($p->telefono ? 5 : 0) + ($p->numero_cuenta ? 3 : 0) + ($p->activo ? 1 : 0);
            })->first();
            foreach ($lista as $dup) {
                if ($dup->id === $keeper->id) {
                    continue;
                }
                DB::table('bodega_movimientos')->where('proveedor_id', $dup->id)->update(['proveedor_id' => $keeper->id]);
                $dup->update([
                    'activo' => false,
                    'observaciones' => trim(($dup->observaciones ? $dup->observaciones . ' | ' : '') . 'Duplicado de ' . $keeper->nombre),
                ]);
            }
        }

        $oficialesLower = array_map(fn ($n) => mb_strtolower($n), $oficiales);
        foreach (BodegaProveedor::where('activo', true)->get() as $prov) {
            $key = mb_strtolower(trim((string) $prov->nombre));
            if (in_array($key, $oficialesLower, true)) {
                continue;
            }
            $prov->update([
                'activo' => false,
                'observaciones' => trim(($prov->observaciones ? $prov->observaciones . ' | ' : '') . 'No está en PROVEEDORES.xlsx'),
            ]);
        }

        foreach (BodegaProveedor::orderBy('id')->get() as $prov) {
            $bodega->asegurarCodigoProveedor($prov);
        }
    }

    private function crearKits(): void
    {
        $agenteItems = $this->resolverProductos([
            ['like' => 'camisa agente', 'cantidad' => 1],
            ['like' => 'pantalón agente (hombre)', 'cantidad' => 1, 'alt' => 'pantalon agente (hombre)'],
            ['like' => 'botas agente', 'cantidad' => 1, 'alt' => 'bota'],
            ['like' => 'chaleco agente', 'cantidad' => 1],
            ['like' => 'gorra', 'cantidad' => 1],
            ['like' => 'gorgorito', 'cantidad' => 1],
            ['like' => 'cincho', 'cantidad' => 1, 'alt' => 'cinchos'],
        ]);

        $adminItems = $this->resolverProductos([
            ['like' => 'blusa polo', 'cantidad' => 1],
            ['like' => 'suéter militar', 'cantidad' => 1, 'alt' => 'sueter militar'],
        ]);

        $this->upsertKit(
            'KIT-AGENTE',
            'Kit agente completo',
            'Plantilla de ingreso: camisa, pantalón, botas, chaleco, gorra y accesorios. En la entrega se elige talla y condición.',
            $agenteItems
        );

        $this->upsertKit(
            'KIT-ADMIN',
            'Kit administración',
            'Plantilla de uniforme administrativo. Se puede editar o armar otra desde cero.',
            $adminItems
        );
    }

    /**
     * @param  array<int, array{like:string, cantidad:int, alt?:string}>  $defs
     * @return array<int, array{producto_id:int, cantidad:int}>
     */
    private function resolverProductos(array $defs): array
    {
        $items = [];
        foreach ($defs as $def) {
            $producto = BodegaProducto::query()
                ->where('activo', true)
                ->where('nombre', 'ilike', '%' . $def['like'] . '%')
                ->orderBy('id')
                ->first();
            if (!$producto && !empty($def['alt'])) {
                $producto = BodegaProducto::query()
                    ->where('activo', true)
                    ->where('nombre', 'ilike', '%' . $def['alt'] . '%')
                    ->orderBy('id')
                    ->first();
            }
            if (!$producto) {
                continue;
            }
            $items[] = ['producto_id' => $producto->id, 'cantidad' => $def['cantidad']];
        }

        return $items;
    }

    private function upsertKit(string $codigo, string $nombre, string $obs, array $items): void
    {
        if (count($items) < 2) {
            $this->command?->warn("Combo {$codigo}: no hay suficientes productos, se omite.");
            return;
        }

        $kit = BodegaKit::updateOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'observaciones' => $obs,
                'activo' => true,
            ]
        );
        $kit->items()->delete();
        foreach ($items as $item) {
            $kit->items()->create($item);
        }
    }

    private function casoLiquidacion(BodegaService $bodega): void
    {
        $persona = Personal::query()
            ->where('apellidos', 'ilike', '%Chub%')
            ->orWhere('nombres', 'ilike', '%Jose Prueba%')
            ->orderBy('id')
            ->first();

        if (!$persona) {
            $this->command?->warn('No se encontró personal de prueba (Jose Chub) para el caso de liquidación.');
            return;
        }

        $ya = BodegaEntrega::query()
            ->where('personal_id', $persona->id)
            ->where('tipo', 'kit')
            ->whereNull('devuelta_at')
            ->where('observaciones', 'like', '%Caso liquidación%')
            ->exists();

        if (!$ya) {
            try {
            $camisa = BodegaProducto::where('nombre', 'ilike', '%camisa agente%')->first();
            $cincho = BodegaProducto::where('nombre', 'ilike', '%cincho%')->first()
                ?: BodegaProducto::where('nombre', 'ilike', '%gorgorito%')->first();

            $items = [];
            foreach ([$camisa, $cincho] as $producto) {
                if (!$producto) {
                    continue;
                }
                $variante = $producto->variantes()
                    ->where('activo', true)
                    ->where('existencia', '>', 0)
                    ->orderByDesc('existencia')
                    ->first();
                if (!$variante) {
                    $variante = $producto->variantes()->where('activo', true)->orderBy('id')->first();
                }
                if ($variante && $variante->existencia < 1) {
                    $bodega->registrarMovimiento([
                        'variante_id' => $variante->id,
                        'tipo' => 'ajuste_inicial',
                        'cantidad' => 1,
                        'observaciones' => 'Stock para caso liquidación',
                    ]);
                    $variante->refresh();
                }
                if ($variante) {
                    $items[] = [
                        'variante_id' => $variante->id,
                        'cantidad' => 1,
                        'precio_unitario' => $producto->precioParaCondicion($variante->condicion) ?: 25,
                    ];
                }
            }

            if ($items) {
                $bodega->crearEntregaCompleta([
                    'personal_id' => $persona->id,
                    'tipo' => 'kit',
                    'cobrar' => true,
                    'observaciones' => 'Caso liquidación: uniforme en poder del agente al suspender.',
                    'items' => $items,
                    'descuento' => [
                        'cuotas_totales' => 2,
                        'descripcion' => 'Uniforme pendiente de liquidación',
                    ],
                ]);
            }
            } catch (\Throwable $e) {
                $this->command?->warn('No se pudo crear la boleta de liquidación: ' . $e->getMessage());
            }
        }

        if ($persona->estado === 'activo') {
            $persona->update(['estado' => 'suspendido']);
            try {
                foreach ($persona->asignacionesActivas()->get() as $asignacion) {
                    $asignacion->finalizar('Suspensión de prueba: uniforme pendiente de devolución (boleta).');
                }
            } catch (\Throwable $e) {
                $this->command?->warn('No se pudieron cerrar asignaciones: ' . $e->getMessage());
            }
        }

        $this->command?->info("Caso liquidación: {$persona->nombres} {$persona->apellidos} ({$persona->estado}).");
    }
}
