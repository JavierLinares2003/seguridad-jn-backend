<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoPago;
use Illuminate\Database\Seeder;

class TipoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Efectivo',
            'Transferencia Bancaria',
            'Cheque',
            'Depósito Bancario',
        ];

        foreach ($tipos as $tipo) {
            TipoPago::firstOrCreate(['nombre' => $tipo]);
        }
    }
}
