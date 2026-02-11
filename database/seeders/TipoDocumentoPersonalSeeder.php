<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoDocumentoPersonal;
use Illuminate\Database\Seeder;

class TipoDocumentoPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'DPI',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Antecedentes Penales',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Antecedentes Policiales',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Curriculum Vitae',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'doc', 'docx'],
            ],
            [
                'nombre' => 'Fotografía',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Carnet IGSS',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Licencia de Conducir',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Licencia de Portación de Armas',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Tarjeta de Salud',
                'requiere_vencimiento' => true,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Constancia de Estudios',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            [
                'nombre' => 'Contrato de Trabajo',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf'],
            ],
            [
                'nombre' => 'Constancia Laboral',
                'requiere_vencimiento' => false,
                'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png'],
            ]
        ];

        foreach ($tipos as $tipo) {
            TipoDocumentoPersonal::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                [
                    'requiere_vencimiento' => $tipo['requiere_vencimiento'],
                    'extensiones_permitidas' => $tipo['extensiones_permitidas'],
                ]
            );
        }
    }
}
