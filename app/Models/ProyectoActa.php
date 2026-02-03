<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoActa extends Model
{
    protected $table = 'proyecto_actas';

    protected $fillable = [
        'proyecto_id',
        'tipo_documento',
        'nombre_firmante',
        'dpi_firmante',
        'puesto_firmante',
        'fecha_firma',
        'fecha_inicio_servicios',
        'archivo_path',
    ];

    protected $casts = [
        'fecha_firma' => 'date',
        'fecha_inicio_servicios' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }
}
