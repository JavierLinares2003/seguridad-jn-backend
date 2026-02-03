<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoDocumentoFacturacion;
use Illuminate\Database\Seeder;

class TipoDocumentoFacturacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            'Factura',
            'Recibo',
        ];

        foreach ($tipos as $nombre) {
            TipoDocumentoFacturacion::firstOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
