<?php

namespace App\Console\Commands;

use App\Models\PersonalDocumento;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActualizarEstadoDocumentos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documentos:actualizar-estado
                            {--personal= : ID del personal específico (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el estado de los documentos según su fecha de vencimiento';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando actualización de estados de documentos...');

        $query = PersonalDocumento::query()
            ->whereNotNull('fecha_vencimiento');

        if ($personalId = $this->option('personal')) {
            $query->where('personal_id', $personalId);
        }

        $documentos = $query->get();

        $contadores = [
            'total' => $documentos->count(),
            'vigentes' => 0,
            'por_vencer' => 0,
            'vencidos' => 0,
            'actualizados' => 0,
        ];

        $hoy = Carbon::today();

        foreach ($documentos as $documento) {
            $estadoAnterior = $documento->estado_documento;
            $vencimiento = Carbon::parse($documento->fecha_vencimiento);
            $diasParaVencer = $hoy->diffInDays($vencimiento, false);

            if ($diasParaVencer < 0) {
                $nuevoEstado = 'vencido';
                $contadores['vencidos']++;
            } elseif ($diasParaVencer <= $documento->dias_alerta_vencimiento) {
                $nuevoEstado = 'por_vencer';
                $contadores['por_vencer']++;
            } else {
                $nuevoEstado = 'vigente';
                $contadores['vigentes']++;
            }

            if ($estadoAnterior !== $nuevoEstado) {
                $documento->estado_documento = $nuevoEstado;
                $documento->save();
                $contadores['actualizados']++;

                $this->line(sprintf(
                    '  - Documento #%d: %s → %s (Personal #%d)',
                    $documento->id,
                    $estadoAnterior,
                    $nuevoEstado,
                    $documento->personal_id
                ));
            }
        }

        $this->newLine();
        $this->info('Resumen de actualización:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Documentos procesados', $contadores['total']],
                ['Vigentes', $contadores['vigentes']],
                ['Por vencer', $contadores['por_vencer']],
                ['Vencidos', $contadores['vencidos']],
                ['Actualizados', $contadores['actualizados']],
            ]
        );

        $this->newLine();
        $this->info('Actualización completada.');

        return Command::SUCCESS;
    }
}
