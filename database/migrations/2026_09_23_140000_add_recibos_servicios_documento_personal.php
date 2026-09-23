<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $tipos = [
            ['nombre' => 'Recibo de Luz', 'requiere_vencimiento' => false],
            ['nombre' => 'Recibo de Agua', 'requiere_vencimiento' => false],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('tipos_documentos_personal')->where('nombre', $tipo['nombre'])->exists();
            if ($existe) {
                continue;
            }

            DB::table('tipos_documentos_personal')->insert([
                'nombre' => $tipo['nombre'],
                'requiere_vencimiento' => $tipo['requiere_vencimiento'],
                'extensiones_permitidas' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tipos_documentos_personal')
            ->whereIn('nombre', ['Recibo de Luz', 'Recibo de Agua'])
            ->delete();
    }
};
