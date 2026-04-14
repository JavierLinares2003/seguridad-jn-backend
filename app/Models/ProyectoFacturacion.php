<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Catalogos\TipoDocumentoFacturacion;
use App\Models\Catalogos\PeriodicidadPago;

class ProyectoFacturacion extends Model
{
    use HasFactory;

    protected $table = 'proyectos_facturacion';

    protected $fillable = [
        'proyecto_id',
        'tipo_documento_facturacion_id',
        'nit_cliente',
        'nombre_facturacion',
        'direccion_facturacion',
        'forma_pago',
        'periodicidad_pago_id',
        'dia_pago',
        'monto_proyecto_total',
        'moneda',
        'aplica_impuesto',
        'porcentaje_impuesto',
        'monto_impuesto',
        'monto_total_con_impuesto',
    ];

    protected $casts = [
        'aplica_impuesto' => 'boolean',
        'porcentaje_impuesto' => 'decimal:2',
        'monto_impuesto' => 'decimal:2',
        'monto_total_con_impuesto' => 'decimal:2',
        'monto_proyecto_total' => 'decimal:2',
    ];

    public function recalcularImpuesto(): void
    {
        $montoBase = (float) ($this->monto_proyecto_total ?? 0);

        if ($this->aplica_impuesto && $this->porcentaje_impuesto > 0) {
            $impuesto = round($montoBase * $this->porcentaje_impuesto / 100, 2);
            $this->monto_impuesto = $impuesto;
            $this->monto_total_con_impuesto = round($montoBase + $impuesto, 2);
        } else {
            $this->monto_impuesto = null;
            $this->monto_total_con_impuesto = $montoBase ?: null;
        }

        $this->saveQuietly();
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function tipoDocumentoFacturacion()
    {
        return $this->belongsTo(TipoDocumentoFacturacion::class);
    }

    public function periodicidadPago()
    {
        return $this->belongsTo(PeriodicidadPago::class);
    }
}
