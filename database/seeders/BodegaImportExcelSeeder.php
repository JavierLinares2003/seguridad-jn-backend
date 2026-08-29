<?php

namespace Database\Seeders;

use App\Models\BodegaCategoria;
use App\Models\BodegaProducto;
use App\Services\BodegaService;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa stock inicial desde INVENTARIO BODEGA 2026.xlsx
 * Prioriza hojas simples (kardex) y genera datos demo de uniforme.
 */
class BodegaImportExcelSeeder extends Seeder
{
    public function run(): void
    {
        $path = env(
            'BODEGA_EXCEL_PATH',
            'C:\\Users\\posad\\Downloads\\INVENTARIO BODEGA 2026.xlsx'
        );

        if (!is_file($path)) {
            $this->command?->warn("Excel no encontrado en: {$path}. Se cargarán datos demo mínimos.");
            $this->seedDemoMinimo();
            return;
        }

        $bodega = app(BodegaService::class);
        $spreadsheet = IOFactory::load($path);

        $this->importSimpleSheet($spreadsheet, 'LIBRERIA', 'libreria', $bodega, false);
        $this->importSimpleSheet($spreadsheet, 'ACCESORIOS PUESTO ', 'accesorios_puesto', $bodega, false);
        $this->importSimpleSheet($spreadsheet, 'LIMPIEZA', 'limpieza', $bodega, false);
        $this->importSimpleSheet($spreadsheet, 'ACCESORIOS UNIFORME', 'accesorios_uniforme', $bodega, true);
        $this->importSimpleSheet($spreadsheet, 'EQUIPO DE LLUVIA', 'equipo_lluvia', $bodega, false);
        $this->importSimpleSheet($spreadsheet, 'MECANICO', 'mecanico', $bodega, false);
        $this->importSueter($spreadsheet, $bodega);
        $this->importProveedores($spreadsheet);
        $this->seedUniformeAgentesDemo($bodega);

        $this->command?->info('Importación de bodega completada.');
    }

    private function importSimpleSheet($spreadsheet, string $sheetName, string $categoriaCodigo, BodegaService $bodega, bool $esUniforme): void
    {
        if (!$spreadsheet->sheetNameExists($sheetName) && !$spreadsheet->getSheetByName($sheetName)) {
            // try trim
            $found = null;
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                if (trim($ws->getTitle()) === trim($sheetName)) {
                    $found = $ws;
                    break;
                }
            }
            if (!$found) {
                $this->command?->warn("Hoja no encontrada: {$sheetName}");
                return;
            }
            $sheet = $found;
        } else {
            $sheet = $spreadsheet->getSheetByName($sheetName);
        }

        $categoria = BodegaCategoria::where('codigo', $categoriaCodigo)->first();
        if (!$categoria || !$sheet) {
            return;
        }

        $highest = $sheet->getHighestRow();
        for ($row = 2; $row <= $highest; $row++) {
            $nombre = trim((string) $sheet->getCell('B' . $row)->getValue());
            if ($nombre === '' || str_contains(mb_strtoupper($nombre), 'TOTAL')) {
                continue;
            }

            $inicial = $this->toInt($sheet->getCell('C' . $row)->getCalculatedValue());
            // Algunas hojas tienen existencia en F
            $existenciaF = $this->toInt($sheet->getCell('F' . $row)->getCalculatedValue());
            $stock = max($inicial, $existenciaF);

            // Detectar NUEVO/USADO en el nombre
            $condicion = null;
            $upper = mb_strtoupper($nombre);
            if (str_contains($upper, 'NUEVA') || str_contains($upper, 'NUEVO')) {
                $condicion = 'nuevo';
            } elseif (str_contains($upper, 'USADA') || str_contains($upper, 'USADO')) {
                $condicion = 'usado';
            }

            // Talla en botas "BOTAS 37"
            $talla = null;
            if (preg_match('/\b(\d{2})\b/', $nombre, $m) && str_contains($upper, 'BOTAS')) {
                $talla = $m[1];
            }

            $producto = BodegaProducto::firstOrCreate(
                [
                    'categoria_id' => $categoria->id,
                    'nombre' => $nombre,
                ],
                [
                    'unidad' => 'unidad',
                    'usa_talla' => $talla !== null,
                    'usa_condicion' => $condicion !== null,
                    'usa_genero' => false,
                    'es_uniforme' => $esUniforme || str_contains($upper, 'GORRA') || str_contains($upper, 'UNIFORME'),
                    'activo' => true,
                ]
            );
            $producto->refresh();
            $bodega->asegurarCodigoProducto($producto);

            $variante = $bodega->upsertVariante($producto, [
                'talla' => $talla,
                'condicion' => $condicion,
                'stock_minimo' => $esUniforme ? 2 : 1,
            ]);

            if ($stock > 0 && $variante->existencia === 0) {
                $bodega->registrarMovimiento([
                    'variante_id' => $variante->id,
                    'tipo' => 'ajuste_inicial',
                    'cantidad' => $stock,
                    'observaciones' => 'Carga desde Excel INVENTARIO BODEGA 2026',
                ]);
            }
        }
    }

    private function importSueter($spreadsheet, BodegaService $bodega): void
    {
        $sheet = $spreadsheet->getSheetByName('SUETER MILITAR');
        $categoria = BodegaCategoria::where('codigo', 'sueter_militar')->first();
        if (!$sheet || !$categoria) {
            return;
        }

        for ($row = 2; $row <= 20; $row++) {
            $nombre = trim((string) $sheet->getCell('B' . $row)->getValue());
            if ($nombre === '' || str_contains(mb_strtoupper($nombre), 'TOTAL')) {
                continue;
            }
            $nuevo = $this->toInt($sheet->getCell('C' . $row)->getCalculatedValue());
            $usado = $this->toInt($sheet->getCell('D' . $row)->getCalculatedValue());
            $talla = null;
            if (preg_match('/\b(XS|S|M|L|XL|2XL|3XL|4XL)\b/i', $nombre, $m)) {
                $talla = strtoupper($m[1]);
            }

            $producto = BodegaProducto::firstOrCreate(
                ['categoria_id' => $categoria->id, 'nombre' => 'Suéter Militar'],
                [
                    'unidad' => 'unidad',
                    'usa_talla' => true,
                    'usa_condicion' => true,
                    'es_uniforme' => true,
                    'activo' => true,
                ]
            );
            $producto->refresh();
            $bodega->asegurarCodigoProducto($producto);

            foreach ([['nuevo', $nuevo], ['usado', $usado]] as [$cond, $qty]) {
                if ($qty <= 0 && $cond === 'usado' && $nuevo <= 0) {
                    // still create variante with 0 for catalog completeness when talla exists
                }
                $variante = $bodega->upsertVariante($producto, [
                    'talla' => $talla,
                    'condicion' => $cond,
                    'stock_minimo' => 1,
                ]);
                if ($qty > 0 && $variante->existencia === 0) {
                    $bodega->registrarMovimiento([
                        'variante_id' => $variante->id,
                        'tipo' => 'ajuste_inicial',
                        'cantidad' => $qty,
                        'observaciones' => 'Carga Suéter Militar desde Excel',
                    ]);
                }
            }
        }
    }

    private function importProveedores($spreadsheet): void
    {
        // Hoja8 del inventario mezcla nombres sueltos (a veces productos).
        // Los proveedores oficiales viven en BodegaProveedoresSeeder / PROVEEDORES.xlsx.
        unset($spreadsheet);
    }

    private function seedUniformeAgentesDemo(BodegaService $bodega): void
    {
        $categoria = BodegaCategoria::where('codigo', 'uniforme_agentes')->first();
        if (!$categoria) {
            return;
        }

        // Evitar duplicar si ya hay productos
        if (BodegaProducto::where('categoria_id', $categoria->id)->exists()) {
            return;
        }

        $defs = [
            [
                'nombre' => 'Pantalón Agente',
                'genero' => 'mujer',
                'tallas' => ['26', '28', '30', '32', '34', '36', '38', '40'],
                'stocks' => ['nuevo' => [0, 0, 8, 4, 3, 8, 1, 5], 'usado' => [0, 0, 0, 0, 0, 0, 0, 0]],
            ],
            [
                'nombre' => 'Pantalón Agente',
                'genero' => 'hombre',
                'tallas' => ['28', '30', '32', '34', '36', '38', '40', '42'],
                'stocks' => ['nuevo' => [2, 5, 6, 4, 3, 2, 1, 1], 'usado' => [1, 2, 1, 0, 1, 0, 0, 0]],
            ],
            [
                'nombre' => 'Botas Agente',
                'genero' => 'unisex',
                'tallas' => ['34', '35', '36', '37', '38', '39', '40', '41', '42'],
                'stocks' => ['nuevo' => [0, 2, 4, 3, 5, 2, 4, 2, 1], 'usado' => [0, 0, 1, 1, 2, 0, 1, 0, 0]],
            ],
            [
                'nombre' => 'Camisa Agente',
                'genero' => 'unisex',
                'tallas' => ['S', 'M', 'L', 'XL', '2XL'],
                'stocks' => ['nuevo' => [5, 8, 6, 4, 2], 'usado' => [2, 3, 2, 1, 0]],
            ],
            [
                'nombre' => 'Chaleco Agente',
                'genero' => 'unisex',
                'tallas' => ['S', 'M', 'L', 'XL'],
                'stocks' => ['nuevo' => [3, 5, 4, 2], 'usado' => [1, 2, 1, 0]],
            ],
        ];

        foreach ($defs as $def) {
            $producto = BodegaProducto::create([
                'categoria_id' => $categoria->id,
                'nombre' => $def['nombre'] . ' (' . ucfirst($def['genero']) . ')',
                'unidad' => 'unidad',
                'usa_talla' => true,
                'usa_condicion' => true,
                'usa_genero' => true,
                'es_uniforme' => true,
                'activo' => true,
            ]);

            foreach ($def['tallas'] as $i => $talla) {
                foreach (['nuevo', 'usado'] as $cond) {
                    $qty = $def['stocks'][$cond][$i] ?? 0;
                    $variante = $bodega->upsertVariante($producto, [
                        'talla' => $talla,
                        'condicion' => $cond,
                        'genero' => $def['genero'],
                        'stock_minimo' => 2,
                    ]);
                    if ($qty > 0) {
                        $bodega->registrarMovimiento([
                            'variante_id' => $variante->id,
                            'tipo' => 'ajuste_inicial',
                            'cantidad' => $qty,
                            'observaciones' => 'Stock demo uniforme agentes',
                        ]);
                    }
                }
            }
        }

        // Categoría admin mínima
        $catAdmin = BodegaCategoria::where('codigo', 'uniforme_admin')->first();
        if ($catAdmin && !BodegaProducto::where('categoria_id', $catAdmin->id)->exists()) {
            $producto = BodegaProducto::create([
                'categoria_id' => $catAdmin->id,
                'nombre' => 'Blusa Polo Administración',
                'unidad' => 'unidad',
                'usa_talla' => true,
                'usa_condicion' => true,
                'usa_genero' => true,
                'es_uniforme' => true,
                'activo' => true,
            ]);
            foreach (['S', 'M', 'L', 'XL'] as $talla) {
                foreach (['nuevo', 'usado'] as $cond) {
                    $variante = $bodega->upsertVariante($producto, [
                        'talla' => $talla,
                        'condicion' => $cond,
                        'genero' => 'mujer',
                        'stock_minimo' => 1,
                    ]);
                    $bodega->registrarMovimiento([
                        'variante_id' => $variante->id,
                        'tipo' => 'ajuste_inicial',
                        'cantidad' => $cond === 'nuevo' ? 4 : 1,
                        'observaciones' => 'Stock demo uniforme admin',
                    ]);
                }
            }
        }
    }

    private function seedDemoMinimo(): void
    {
        $this->seedUniformeAgentesDemo(app(BodegaService::class));
    }

    private function toInt($value): int
    {
        if ($value === null || $value === '' || $value === '#REF!' || $value === '#VALUE!' || $value === '#N/A') {
            return 0;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }
}
