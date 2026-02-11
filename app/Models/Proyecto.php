<?php

namespace App\Models;

use App\Models\Catalogos\TipoProyecto;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proyecto extends Model
{
    use HasFactory, SoftDeletes, AuditableModel;

    protected string $logName = 'proyectos';
    protected string $modulo = 'proyectos';

    protected $table = 'proyectos';

    protected $fillable = [
        'tipo_proyecto_id',
        'correlativo',
        'nombre_proyecto',
        'descripcion',
        'empresa_cliente',
        'estado_proyecto',
        'fecha_inicio_estimada',
        'fecha_fin_estimada',
        'fecha_inicio_real',
        'fecha_fin_real',
    ];

    protected $casts = [
        'fecha_inicio_estimada' => 'date',
        'fecha_fin_estimada' => 'date',
        'fecha_inicio_real' => 'date',
        'fecha_fin_real' => 'date',
    ];

    public function tipoProyecto()
    {
        return $this->belongsTo(TipoProyecto::class);
    }

    public function ubicacion()
    {
        return $this->hasOne(ProyectoUbicacion::class);
    }

    public function facturacion()
    {
        return $this->hasOne(ProyectoFacturacion::class);
    }

    public function contactos()
    {
        return $this->hasMany(ProyectoContacto::class);
    }

    public function inventario()
    {
        return $this->hasMany(ProyectoInventario::class);
    }

    public function configuracionPersonal()
    {
        return $this->hasMany(ProyectoConfiguracionPersonal::class);
    }

    public function documentos()
    {
        return $this->hasMany(ProyectoDocumento::class);
    }
}
