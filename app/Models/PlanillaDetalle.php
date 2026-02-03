<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaDetalle extends Model
{
    protected $table = 'planillas_detalle';
    
    protected $fillable = [
        'planilla_id',
        'personal_id',
        'proyecto_id',
        'dias_trabajados',
        'horas_trabajadas',
        'pago_por_hora',
        'salario_devengado',
        'bonificacion',
        'horas_extra',
        'descuento_multas',
        'descuento_uniformes',
        'descuento_anticipos',
        'descuento_prestamos',
        'descuento_antecedentes',
        'otros_descuentos',
        'total_descuentos',
        'salario_neto',
        'observaciones',
    ];
    
    protected $casts = [
        'dias_trabajados' => 'integer',
        'horas_trabajadas' => 'decimal:2',
        'pago_por_hora' => 'decimal:2',
        'salario_devengado' => 'decimal:2',
        'bonificacion' => 'decimal:2',
        'horas_extra' => 'decimal:2',
        'descuento_multas' => 'decimal:2',
        'descuento_uniformes' => 'decimal:2',
        'descuento_anticipos' => 'decimal:2',
        'descuento_prestamos' => 'decimal:2',
        'descuento_antecedentes' => 'decimal:2',
        'otros_descuentos' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'salario_neto' => 'decimal:2',
    ];
    
    /**
     * Relación con planilla
     */
    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class);
    }
    
    /**
     * Relación con personal
     */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }
    
    /**
     * Relación con proyecto
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
    
    /**
     * Accessor para nombre completo del empleado
     */
    public function getNombreCompletoAttribute(): string
    {
        return $this->personal ? 
            "{$this->personal->nombres} {$this->personal->apellidos}" : 
            '';
    }
    
    /**
     * Accessor para total de ingresos
     */
    public function getTotalIngresosAttribute(): float
    {
        return $this->salario_devengado + $this->bonificacion + $this->horas_extra;
    }
    
    /**
     * Verificar si tiene descuentos
     */
    public function tieneDescuentos(): bool
    {
        return $this->total_descuentos > 0;
    }
}
