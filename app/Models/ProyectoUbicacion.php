<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Catalogos\DepartamentoGeografico;
use App\Models\Catalogos\Municipio;

class ProyectoUbicacion extends Model
{
    use HasFactory;

    protected $table = 'proyectos_ubicaciones';

    protected $fillable = [
        'proyecto_id',
        'departamento_geo_id',
        'municipio_id',
        'zona',
        'direccion_completa',
        'coordenadas_gps',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function departamentoGeografico()
    {
        return $this->belongsTo(DepartamentoGeografico::class, 'departamento_geo_id');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }
}
