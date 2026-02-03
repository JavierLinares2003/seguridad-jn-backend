<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProyectoActaController extends Controller
{
    use \Illuminate\Foundation\Validation\ValidatesRequests;

    public function index($proyectoId)
    {
        $actas = \App\Models\ProyectoActa::where('proyecto_id', $proyectoId)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($actas);
    }

    public function store(Request $request, $proyectoId)
    {
        $validated = $request->validate([
            'nombre_firmante' => 'required|string',
            'dpi_firmante' => 'required|string',
            'puesto_firmante' => 'required|string',
            'fecha_firma' => 'required|date',
            'fecha_inicio_servicios' => 'required|date',
        ]);

        $proyecto = \App\Models\Proyecto::with(['ubicacion', 'contactos'])->findOrFail($proyectoId);

        // Generar PDF y guardar path
        $pdfContent = $this->generatePdfContent($proyecto, $validated);
        $filename = 'ACTA_' . $proyectoId . '_' . time() . '.pdf';
        $path = 'proyectos/actas/' . $filename;
        
        \Illuminate\Support\Facades\Storage::put('private/' . $path, $pdfContent); // private storage

        $acta = \App\Models\ProyectoActa::create([
            'proyecto_id' => $proyectoId,
            'nombre_firmante' => $validated['nombre_firmante'],
            'dpi_firmante' => $validated['dpi_firmante'],
            'puesto_firmante' => $validated['puesto_firmante'],
            'fecha_firma' => $validated['fecha_firma'],
            'fecha_inicio_servicios' => $validated['fecha_inicio_servicios'],
            'archivo_path' => $path,
        ]);

        return response()->json($acta, 201);
    }

    public function download($proyectoId, $id)
    {
        $acta = \App\Models\ProyectoActa::where('proyecto_id', $proyectoId)
            ->where('id', $id)
            ->firstOrFail();
        
        if (!$acta->archivo_path || !\Illuminate\Support\Facades\Storage::exists('private/' . $acta->archivo_path)) {
            // Si no existe, intentar regenerarlo? Por ahora error
             return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return \Illuminate\Support\Facades\Storage::download('private/' . $acta->archivo_path);
    }

    private function generatePdfContent($proyecto, $data)
    {
        // Calcular datos adicionales
        $configuraciones = $proyecto->configuracionPersonal()->with('turno')->get();
        
        $totalAgentes = $configuraciones->sum('cantidad_requerida');
        // Asumimos el primer turno encontrado o concatenamos si son varios
        $turnos = $configuraciones->pluck('turno.nombre')->unique()->filter()->join(', ');
        $turno = $turnos ?: 'N/A';
        
        $facturacion = $proyecto->facturacion;
        $formaPago = $facturacion ? strtolower($facturacion->forma_pago) : '';
        $costoTotal = $facturacion ? $facturacion->monto_proyecto_total : 0;
        
        // Precio Promedio por Agente (Costo Total / Total Agentes)
        $precioAgente = $totalAgentes > 0 ? $costoTotal / $totalAgentes : 0;

        // Estructurar datos como objeto esperado por la vista
        $aceptacion = (object) [
            'fecha_aceptacion' => \Carbon\Carbon::parse($data['fecha_firma']),
            'firmante_nombre' => $data['nombre_firmante'],
            'firmante_dpi' => $data['dpi_firmante'],
            'firmante_puesto' => $data['puesto_firmante'],
            'fecha_inicio_servicio' => \Carbon\Carbon::parse($data['fecha_inicio_servicios']),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.proyecto.acta_inicio', [
            'proyecto' => $proyecto,
            'aceptacion' => $aceptacion,
            'totalAgentes' => $totalAgentes,
            'turno' => $turno,
            'formaPago' => $formaPago,
            'precioAgente' => $precioAgente,
            'costoTotal' => $costoTotal
        ]);
        
        $pdf->setPaper('letter', 'portrait');
        return $pdf->output();
    }
}
