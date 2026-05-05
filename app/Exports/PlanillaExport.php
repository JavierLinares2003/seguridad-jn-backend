<?php

namespace App\Exports;

use App\Models\Planilla;
use App\Models\Transaccion;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PlanillaExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    private Planilla $planilla;
    private int $filaInicio = 6; // Fila donde empieza la tabla de datos

    public function __construct(Planilla $planilla)
    {
        // Cargar todas las relaciones necesarias
        $planilla->load([
            'detalles.personal',
            'detalles.proyecto',
            'creadoPor',
            'aprobadoPor',
            'proyecto',
            'departamento',
        ]);

        // Agregar transacciones a cada detalle
        $planilla->detalles->each(function ($detalle) use ($planilla) {
            $detalle->transacciones = Transaccion::where('personal_id', $detalle->personal_id)
                ->whereBetween('fecha_transaccion', [
                    $planilla->periodo_inicio,
                    $planilla->periodo_fin,
                ])
                ->where('es_descuento', true)
                ->get();
        });

        $this->planilla = $planilla;
    }

    public function array(): array
    {
        $rows = [];
        $p = $this->planilla;

        // --- Encabezado informativo ---
        $ambito = $p->proyecto?->nombre_proyecto
            ?? $p->departamento?->nombre
            ?? 'General';

        $rows[] = ['PLANILLA DE SUELDOS - SEGURIDAD JN'];
        $rows[] = ['Planilla:', $p->nombre_planilla];
        $rows[] = ['Período:', $p->periodo_inicio->format('d/m/Y') . ' al ' . $p->periodo_fin->format('d/m/Y')];
        $rows[] = ['Ámbito:', $ambito];
        $rows[] = ['Estado:', strtoupper($p->estado_planilla)];
        $rows[] = []; // Fila vacía antes de la tabla

        // --- Encabezados de la tabla ---
        // Columnas: A-T (20 columnas)
        $rows[] = [
            '#',                  // A
            'Empleado',           // B
            'Proyecto',           // C
            'Días Trabajados',    // D
            'Días Descanso',      // E
            'Días Ausentes',      // F
            'Horas Trabajadas',   // G
            'Salario Esperado',   // H
            'Salario Devengado',  // I
            'Bonificación',       // J
            'Desc. Ausencias',    // K
            'Desc. IGSS',         // L
            'Desc. Multas',       // M
            'Desc. Uniformes',    // N
            'Desc. Anticipos',    // O
            'Desc. Préstamos',    // P
            'Desc. Antecedentes', // Q
            'Otros Descuentos',   // R
            'Total Descuentos',   // S
            'Salario Neto',       // T
        ];

        // --- Filas de detalle ---
        $numero = 1;
        foreach ($p->detalles as $detalle) {
            $rows[] = [
                $numero++,
                $detalle->personal
                    ? trim($detalle->personal->nombres . ' ' . $detalle->personal->apellidos)
                    : 'N/A',
                $detalle->proyecto?->nombre_proyecto ?? '—',
                $detalle->dias_trabajados,
                $detalle->dias_descanso,
                $detalle->dias_ausentes,
                (float) $detalle->horas_trabajadas,
                $detalle->salario_esperado !== null ? (float) $detalle->salario_esperado : '—',
                (float) $detalle->salario_devengado,
                (float) $detalle->bonificacion,
                (float) $detalle->descuento_ausencias,
                (float) $detalle->descuento_igss,
                (float) $detalle->descuento_multas,
                (float) $detalle->descuento_uniformes,
                (float) $detalle->descuento_anticipos,
                (float) $detalle->descuento_prestamos,
                (float) $detalle->descuento_antecedentes,
                (float) $detalle->otros_descuentos,
                (float) $detalle->total_descuentos,
                (float) $detalle->salario_neto,
            ];
        }

        // --- Fila de totales ---
        $rows[] = [
            '',
            'TOTALES',
            '',
            $p->detalles->sum('dias_trabajados'),   // D
            $p->detalles->sum('dias_descanso'),      // E
            $p->detalles->sum('dias_ausentes'),      // F
            '',                                      // G horas
            (float) $p->detalles->sum('salario_esperado'),  // H
            (float) $p->total_devengado,             // I
            '',                                      // J bonificacion
            (float) $p->detalles->sum('descuento_ausencias'), // K
            (float) $p->detalles->sum('descuento_igss'),      // L
            (float) $p->detalles->sum('descuento_multas'),    // M
            (float) $p->detalles->sum('descuento_uniformes'), // N
            (float) $p->detalles->sum('descuento_anticipos'), // O
            (float) $p->detalles->sum('descuento_prestamos'), // P
            (float) $p->detalles->sum('descuento_antecedentes'), // Q
            (float) $p->detalles->sum('otros_descuentos'),    // R
            (float) $p->total_descuentos,            // S
            (float) $p->total_neto,                  // T
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Planilla';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // #
            'B' => 30,  // Empleado
            'C' => 22,  // Proyecto
            'D' => 16,  // Días Trabajados
            'E' => 14,  // Días Descanso
            'F' => 14,  // Días Ausentes
            'G' => 16,  // Horas Trabajadas
            'H' => 18,  // Salario Esperado
            'I' => 18,  // Salario Devengado
            'J' => 14,  // Bonificación
            'K' => 16,  // Desc. Ausencias
            'L' => 14,  // Desc. IGSS
            'M' => 14,  // Desc. Multas
            'N' => 16,  // Desc. Uniformes
            'O' => 16,  // Desc. Anticipos
            'P' => 16,  // Desc. Préstamos
            'Q' => 18,  // Desc. Antecedentes
            'R' => 16,  // Otros Descuentos
            'S' => 16,  // Total Descuentos
            'T' => 14,  // Salario Neto
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalFilas = $this->filaInicio + $this->planilla->detalles->count();
        $filaTotal  = $totalFilas + 1;

        return [
            // Título principal
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Filas de info (2-5)
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true]],
            // Encabezados de la tabla
            $this->filaInicio => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ],
            // Fila de totales
            $filaTotal => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalFilas = $this->filaInicio + $this->planilla->detalles->count();
                $filaTotal  = $totalFilas + 1;
                $lastCol    = 'T';

                // Combinar celdas del título
                $sheet->mergeCells("A1:{$lastCol}1");

                // Formato moneda para columnas H-T (salario_esperado … salario_neto)
                $colsMoneda = ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
                foreach ($colsMoneda as $col) {
                    $sheet->getStyle("{$col}{$this->filaInicio}:{$col}{$filaTotal}")
                        ->getNumberFormat()
                        ->setFormatCode('Q#,##0.00');
                }

                // Bordes en la tabla completa
                $sheet->getStyle("A{$this->filaInicio}:{$lastCol}{$filaTotal}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Alternado de filas (filas pares de datos)
                $primerFila = $this->filaInicio + 1;
                for ($fila = $primerFila; $fila < $filaTotal; $fila++) {
                    if (($fila - $primerFila) % 2 === 1) {
                        $sheet->getStyle("A{$fila}:{$lastCol}{$fila}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('DEEAF1');
                    }
                }

                // Centrar columnas A, D, E, F, G (numericas enteras)
                $sheet->getStyle("A{$primerFila}:A{$filaTotal}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$primerFila}:G{$filaTotal}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Altura de la fila de encabezados
                $sheet->getRowDimension($this->filaInicio)->setRowHeight(35);

                // Freeze pane (bloquear encabezados)
                $sheet->freezePane("A" . ($this->filaInicio + 1));
            },
        ];
    }
}
