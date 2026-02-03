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
    ];

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
