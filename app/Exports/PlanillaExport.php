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
        $rows[] = [
            '#',
            'Empleado',
            'Proyecto',
            'Días Trabajados',
            'Horas Trabajadas',
            'Salario Devengado',
            'Bonificación',
            'Desc. Multas',
            'Desc. Uniformes',
            'Desc. Anticipos',
            'Desc. Préstamos',
            'Desc. Antecedentes',
            'Otros Descuentos',
            'Total Descuentos',
            'Salario Neto',
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
                (float) $detalle->horas_trabajadas,
                (float) $detalle->salario_devengado,
                (float) $detalle->bonificacion,
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
            $p->detalles->sum('dias_trabajados'),
            '',
            (float) $p->total_devengado,
            '',
            (float) $p->detalles->sum('descuento_multas'),
            (float) $p->detalles->sum('descuento_uniformes'),
            (float) $p->detalles->sum('descuento_anticipos'),
            (float) $p->detalles->sum('descuento_prestamos'),
            (float) $p->detalles->sum('descuento_antecedentes'),
            (float) $p->detalles->sum('otros_descuentos'),
            (float) $p->total_descuentos,
            (float) $p->total_neto,
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
            'A' => 5,
            'B' => 30,
            'C' => 22,
            'D' => 16,
            'E' => 16,
            'F' => 18,
            'G' => 14,
            'H' => 14,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 18,
            'M' => 16,
            'N' => 16,
            'O' => 14,
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
                $lastCol    = 'O';

                // Combinar celdas del título
                $sheet->mergeCells("A1:{$lastCol}1");

                // Formato moneda para columnas numéricas (F, G, H, I, J, K, L, M, N, O)
                $colsMoneda = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
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

                // Centrar columnas A, D, E
                $sheet->getStyle("A{$primerFila}:A{$filaTotal}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$primerFila}:E{$filaTotal}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Altura de la fila de encabezados
                $sheet->getRowDimension($this->filaInicio)->setRowHeight(35);

                // Freeze pane (bloquear encabezados)
                $sheet->freezePane("A" . ($this->filaInicio + 1));
            },
        ];
    }
}
