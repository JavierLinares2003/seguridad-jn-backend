<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectoInventario extends Model
{
    use HasFactory;

    protected $table = 'proyectos_inventario';

    protected $fillable = [
        'proyecto_id',
        'codigo_inventario',
        'nombre_item',
        'cantidad_asignada',
        'estado_item',
        'fecha_asignacion',
        'fecha_devolucion',
        'observaciones',
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'fecha_devolucion' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }
}
