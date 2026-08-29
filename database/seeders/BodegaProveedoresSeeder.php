<?php

namespace Database\Seeders;

use App\Models\BodegaProducto;
use App\Models\BodegaProveedor;
use Illuminate\Database\Seeder;

class BodegaProveedoresSeeder extends Seeder
{
    public function run(): void
    {
        $proveedores = [
            ['nombre' => 'Industrial Magaña', 'insumo' => 'Estufas y linternas', 'numero_cuenta' => '3414082171', 'banco' => 'Banrural', 'telefono' => '4084-8601'],
            ['nombre' => 'Gran Ahorro', 'insumo' => 'Limpieza', 'numero_cuenta' => '006-030302-1', 'banco' => 'BI', 'telefono' => '3138-0556'],
            ['nombre' => 'Sergio Ruano', 'insumo' => 'Uniformes', 'numero_cuenta' => '1490304191', 'banco' => 'BI', 'telefono' => '3062-3035'],
            ['nombre' => 'Distribuidora Julissa', 'insumo' => 'Pantalón lona', 'numero_cuenta' => '902290063', 'banco' => 'BAC', 'telefono' => '5011-6853'],
            ['nombre' => 'Ismael', 'insumo' => 'Galeón y corporación', 'numero_cuenta' => '694-006380-0', 'banco' => 'BI', 'telefono' => '5678-2427'],
            ['nombre' => 'Librería Progreso', 'insumo' => 'Librería', 'numero_cuenta' => '0036142020', 'banco' => 'BI', 'telefono' => '3068-2799'],
            ['nombre' => 'Saida', 'insumo' => 'Insumos de puesto', 'numero_cuenta' => '0972972', 'banco' => 'BI', 'telefono' => '5213-7307'],
            ['nombre' => 'José Recinos', 'insumo' => 'Botas', 'numero_cuenta' => '3350140566', 'banco' => 'BI', 'telefono' => '5512-1643'],
            ['nombre' => 'Distribuidora Jerusalem', 'insumo' => 'Uniformes JN', 'numero_cuenta' => null, 'banco' => 'Banrural', 'telefono' => '5932-5342'],
            ['nombre' => 'MB Printing', 'insumo' => 'Stickers', 'numero_cuenta' => '03968500040643', 'banco' => 'Banrural', 'telefono' => '5305-3468'],
            ['nombre' => 'Norma', 'insumo' => 'Galeón', 'numero_cuenta' => '003-005606-3', 'banco' => null, 'telefono' => '5333-1436'],
            ['nombre' => 'Guillermo Palacios', 'insumo' => 'Calendarios y boletas', 'numero_cuenta' => '12121010143355', 'banco' => 'Promerica', 'telefono' => '4707-5397'],
            ['nombre' => 'GEB', 'insumo' => 'Uniformes administración', 'numero_cuenta' => '3414093631', 'banco' => 'Banrural', 'telefono' => '5538-8131'],
            ['nombre' => 'Alejandro', 'insumo' => 'Uniformes JN', 'numero_cuenta' => '1880151533', 'banco' => 'BI', 'telefono' => '3403-5189'],
            ['nombre' => 'Mario Grijalva', 'insumo' => 'Uniformes administración', 'numero_cuenta' => '3414093631', 'banco' => 'Banrural', 'telefono' => '5306-5087'],
            ['nombre' => 'Kevin Roberto Elieser Cumes', 'insumo' => 'Uniformes Jerusalem', 'numero_cuenta' => '3018336618', 'banco' => 'Banrural', 'telefono' => '4176-0673'],
            ['nombre' => 'Francisco Javier', 'insumo' => 'Técnico', 'numero_cuenta' => '967262049', 'banco' => 'BAC', 'telefono' => null],
        ];

        foreach ($proveedores as $row) {
            $prov = BodegaProveedor::updateOrCreate(
                ['nombre' => $row['nombre']],
                array_merge($row, ['activo' => true])
            );
            app(\App\Services\BodegaService::class)->asegurarCodigoProveedor($prov);
        }

        $precios = [
            ['like' => 'camisa', 'categoria' => 'uniforme_agentes', 'nuevo' => 95, 'usado' => 47.5],
            ['like' => 'pantalón de lona', 'categoria' => null, 'nuevo' => 95, 'usado' => 47.5],
            ['like' => 'pantalon de lona', 'categoria' => null, 'nuevo' => 95, 'usado' => 47.5],
            ['like' => 'pantalón', 'categoria' => 'uniforme_agentes', 'nuevo' => 95, 'usado' => 47.5],
            ['like' => 'pantalon', 'categoria' => 'uniforme_agentes', 'nuevo' => 95, 'usado' => 47.5],
            ['like' => 'chaleco', 'categoria' => 'uniforme_agentes', 'nuevo' => 85, 'usado' => 42.5],
            ['like' => 'bota', 'categoria' => 'uniforme_agentes', 'nuevo' => 200, 'usado' => 100],
            ['like' => 'gorra', 'categoria' => 'uniforme_agentes', 'nuevo' => 55, 'usado' => 22.5],
            ['like' => 'gorgorito', 'categoria' => null, 'nuevo' => 25, 'usado' => 25],
            ['like' => 'cincho', 'categoria' => 'accesorios_uniforme', 'nuevo' => 25, 'usado' => 25],
            ['like' => 'gafete', 'categoria' => null, 'nuevo' => 25, 'usado' => 25],
            ['like' => 'suéter', 'categoria' => null, 'nuevo' => 120, 'usado' => 120],
            ['like' => 'sueter', 'categoria' => null, 'nuevo' => 120, 'usado' => 120],
            ['like' => 'playera', 'categoria' => null, 'nuevo' => 25, 'usado' => 25],
            ['like' => 'blusa', 'categoria' => 'uniforme_admin', 'nuevo' => 145, 'usado' => null],
            ['like' => 'polo', 'categoria' => 'uniforme_admin', 'nuevo' => 95, 'usado' => null],
            ['like' => 'columbia', 'categoria' => 'uniforme_admin', 'nuevo' => 185, 'usado' => null],
            ['like' => 'chumpa', 'categoria' => 'uniforme_admin', 'nuevo' => 195, 'usado' => null],
        ];

        foreach ($precios as $precio) {
            $query = BodegaProducto::query()->where('nombre', 'ilike', '%' . $precio['like'] . '%');
            if ($precio['categoria']) {
                $query->whereHas('categoria', fn ($q) => $q->where('codigo', $precio['categoria']));
            }
            $query->where(function ($q) {
                $q->whereNull('precio_venta')->orWhere('precio_venta', 0);
            })->update([
                'precio_venta' => $precio['nuevo'],
                'precio_usado' => $precio['usado'],
            ]);
        }
    }
};
