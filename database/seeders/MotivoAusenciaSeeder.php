<?php

namespace Database\Seeders;

use App\Models\Catalogos\MotivoAusencia;
use Illuminate\Database\Seeder;

class MotivoAusenciaSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            [
                'nombre' => 'Enfermedad',
                'descripcion' => 'Ausencia por enfermedad del trabajador',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => true,
            ],
            [
                'nombre' => 'Cita Médica',
                'descripcion' => 'Asistencia a cita médica programada',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => true,
            ],
            [
                'nombre' => 'Fallecimiento Familiar',
                'descripcion' => 'Ausencia por fallecimiento de familiar cercano',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => true,
            ],
            [
                'nombre' => 'Permiso Personal',
                'descripcion' => 'Permiso personal autorizado por supervisor',
                'es_justificada' => true,
                'aplica_descuento' => true,
                'requiere_documento' => false,
            ],
            [
                'nombre' => 'Trámites Legales',
                'descripcion' => 'Asistencia a citaciones judiciales o trámites legales',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => true,
            ],
            [
                'nombre' => 'Vacaciones',
                'descripcion' => 'Periodo de vacaciones autorizadas',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => false,
            ],
            [
                'nombre' => 'Licencia IGSS',
                'descripcion' => 'Licencia por IGSS (Instituto Guatemalteco de Seguridad Social)',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => true,
            ],
            [
                'nombre' => 'Capacitación',
                'descripcion' => 'Asistencia a capacitación autorizada',
                'es_justificada' => true,
                'aplica_descuento' => false,
                'requiere_documento' => false,
            ],
            [
                'nombre' => 'Sin Justificación',
                'descripcion' => 'Ausencia sin justificación válida',
                'es_justificada' => false,
                'aplica_descuento' => true,
                'requiere_documento' => false,
            ],
            [
                'nombre' => 'Abandono de Puesto',
                'descripcion' => 'Abandono del puesto de trabajo sin autorización',
                'es_justificada' => false,
                'aplica_descuento' => true,
                'requiere_documento' => false,
            ],
            [
                'nombre' => 'Suspensión',
                'descripcion' => 'Suspensión disciplinaria',
                'es_justificada' => false,
                'aplica_descuento' => true,
                'requiere_documento' => false,
            ],
        ];

        foreach ($motivos as $motivo) {
            MotivoAusencia::firstOrCreate(
                ['nombre' => $motivo['nombre']],
                $motivo
            );
        }
    }
}
