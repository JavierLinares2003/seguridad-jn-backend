<?php

namespace Database\Seeders;

use App\Models\Catalogos\Departamento;
use Illuminate\Database\Seeder;

class DepartamentoSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            'Administración',
            'Recursos Humanos',
            'Operaciones',
            'Finanzas',
            'Contabilidad',
            'Logística',
            'Seguridad',
            'Tecnología',
            'Gerencia General',
        ];

        foreach ($departamentos as $depto) {
            Departamento::firstOrCreate(['nombre' => $depto]);
        }
    }
}
