<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectoConfiguracionPersonal extends Model
{
    use HasFactory;

    protected $table = 'proyectos_configuracion_personal';

    protected $fillable = [
        'proyecto_id',
        'nombre_puesto', // Now nullable
        'cantidad_requerida',
        'edad_minima',
        'edad_maxima',
        'sexo_id',
        'altura_minima',
        'estudio_minimo_id',
        'tipo_personal_id',
        'turno_id',
        'costo_hora_proyecto',
        'pago_hora_personal',
        'estado',
    ];

    protected $casts = [
        'costo_hora_proyecto' => 'decimal:2',
        'pago_hora_personal' => 'decimal:2',
        'margen_utilidad' => 'decimal:2',
        'altura_minima' => 'decimal:2',
    ];

    // Relations
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function tipoPersonal()
    {
        return $this->belongsTo('App\Models\Catalogos\TipoPersonal', 'tipo_personal_id');
    }

    public function sexo()
    {
        return $this->belongsTo('App\Models\Catalogos\Sexo', 'sexo_id');
    }

    public function turno()
    {
        return $this->belongsTo('App\Models\Catalogos\Turno', 'turno_id');
    }

    public function estudioMinimo()
    {
        return $this->belongsTo('App\Models\Catalogos\NivelEstudio', 'estudio_minimo_id');
    }

    // Accessor for salario_base (using pago_hora_personal as base)
    public function getSalarioBaseAttribute()
    {
        return $this->pago_hora_personal;
    }
}
