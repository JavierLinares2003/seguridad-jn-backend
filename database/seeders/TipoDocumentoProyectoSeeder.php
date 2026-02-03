<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoDocumentoProyecto;
use Illuminate\Database\Seeder;

class TipoDocumentoProyectoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'Contrato de Servicios',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Cotización',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'xlsx', 'xls'],
            ],
            [
                'nombre' => 'Orden de Compra',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Acta de Inicio',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Acta de Recepción',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Planos/Croquis',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png', 'dwg'],
            ],
            [
                'nombre' => 'Fianza de Cumplimiento',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Póliza de Seguro',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Informe de Servicio',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'doc', 'docx'],
            ],
            [
                'nombre' => 'Factura',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'xml'],
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumentoProyecto::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                [
                    'requiere_vencimiento' => $tipo['requiere_vencimiento'],
                    'extensiones_permitidas' => $tipo['extensiones_permitidas'],
                ]
            );
        }
    }
}
