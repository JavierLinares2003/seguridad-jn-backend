<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_proveedores', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->unique()->after('id');
        });

        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->timestamp('devuelta_at')->nullable()->after('fecha_entrega');
        });

        Schema::table('bodega_entrega_items', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_devuelta')->default(0)->after('cantidad');
        });

        $this->backfillProductos();
        $this->backfillSkus();
        $this->backfillProveedores();
    }

    public function down(): void
    {
        Schema::table('bodega_entrega_items', function (Blueprint $table) {
            $table->dropColumn('cantidad_devuelta');
        });
        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->dropColumn('devuelta_at');
        });
        Schema::table('bodega_proveedores', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }

    private function backfillProductos(): void
    {
        $secuencias = [];
        $existentes = DB::table('bodega_productos')
            ->whereNotNull('codigo')
            ->where('codigo', '<>', '')
            ->get(['codigo']);

        foreach ($existentes as $row) {
            if (preg_match('/^([A-Z]+)-(\d+)$/i', (string) $row->codigo, $m)) {
                $prefijo = strtoupper($m[1]);
                $secuencias[$prefijo] = max($secuencias[$prefijo] ?? 0, (int) $m[2]);
            }
        }

        $productos = DB::table('bodega_productos as p')
            ->leftJoin('bodega_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where(function ($q) {
                $q->whereNull('p.codigo')->orWhere('p.codigo', '');
            })
            ->orderBy('p.id')
            ->get(['p.id', 'c.prefijo_correlativo']);

        foreach ($productos as $producto) {
            $prefijo = strtoupper((string) ($producto->prefijo_correlativo ?: 'BOD'));
            $n = ($secuencias[$prefijo] ?? 0) + 1;
            do {
                $codigo = $prefijo . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $n++;
            } while (DB::table('bodega_productos')->where('codigo', $codigo)->exists());
            $secuencias[$prefijo] = $n - 1;
            DB::table('bodega_productos')->where('id', $producto->id)->update(['codigo' => $codigo]);
        }
    }

    private function backfillSkus(): void
    {
        $variantes = DB::table('bodega_variantes as v')
            ->join('bodega_productos as p', 'p.id', '=', 'v.producto_id')
            ->where(function ($q) {
                $q->whereNull('v.sku')->orWhere('v.sku', '');
            })
            ->whereNotNull('p.codigo')
            ->where('p.codigo', '<>', '')
            ->orderBy('v.id')
            ->get(['v.id', 'v.talla', 'v.condicion', 'v.genero', 'p.codigo']);

        foreach ($variantes as $variante) {
            $parts = [$variante->codigo];
            if ($variante->talla) {
                $parts[] = strtoupper((string) $variante->talla);
            }
            if ($variante->genero) {
                $parts[] = strtoupper(substr((string) $variante->genero, 0, 1));
            }
            if ($variante->condicion) {
                $parts[] = $variante->condicion === 'usado' ? 'U' : 'N';
            }
            $base = implode('-', $parts);
            $sku = $base;
            $i = 2;
            while (DB::table('bodega_variantes')->where('sku', $sku)->exists()) {
                $sku = $base . '-' . $i;
                $i++;
            }
            DB::table('bodega_variantes')->where('id', $variante->id)->update(['sku' => $sku]);
        }
    }

    private function backfillProveedores(): void
    {
        $n = (int) DB::table('bodega_proveedores')
            ->whereNotNull('codigo')
            ->where('codigo', 'like', 'PRV-%')
            ->selectRaw("COALESCE(MAX(NULLIF(regexp_replace(SPLIT_PART(codigo, '-', 2), '[^0-9]', '', 'g'), '')::INTEGER), 0) as max")
            ->value('max');

        $proveedores = DB::table('bodega_proveedores')
            ->where(function ($q) {
                $q->whereNull('codigo')->orWhere('codigo', '');
            })
            ->orderBy('id')
            ->get(['id']);

        foreach ($proveedores as $proveedor) {
            $n++;
            $codigo = 'PRV-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            while (DB::table('bodega_proveedores')->where('codigo', $codigo)->exists()) {
                $n++;
                $codigo = 'PRV-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }
            DB::table('bodega_proveedores')->where('id', $proveedor->id)->update(['codigo' => $codigo]);
        }
    }
};
