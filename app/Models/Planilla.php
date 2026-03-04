<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Catalogos\Departamento;

class Planilla extends Model
{
    use AuditableModel;

    protected string $logName = 'planillas';
    protected string $modulo = 'planillas';
    protected $table = 'planillas';
    
    protected $fillable = [
        'nombre_planilla',
        'periodo_inicio',
        'periodo_fin',
        'total_devengado',
        'total_descuentos',
        'total_neto',
        'estado_planilla',
        'creado_por_user_id',
        'aprobado_por_user_id',
        'fecha_aprobacion',
        'observaciones',
        'tipo_calculo',
        'proyecto_id',
        'departamento_id',
    ];
    
    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'total_devengado' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_neto' => 'decimal:2',
        'fecha_aprobacion' => 'datetime',
    ];
    
    /**
     * Relación con detalles de planilla
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PlanillaDetalle::class);
    }
    
    /**
     * Usuario que creó la planilla
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
    
    /**
     * Usuario que aprobó la planilla
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }

    /**
     * Proyecto asociado a la planilla
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * Departamento asociado a la planilla
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }
    
    /**
     * Scope para planillas en borrador
     */
    public function scopeBorrador($query)
    {
        return $query->where('estado_planilla', 'borrador');
    }
    
    /**
     * Scope para planillas aprobadas
     */
    public function scopeAprobada($query)
    {
        return $query->where('estado_planilla', 'aprobada');
    }
    
    /**
     * Scope para planillas pagadas
     */
    public function scopePagada($query)
    {
        return $query->where('estado_planilla', 'pagada');
    }
    
    /**
     * Scope para filtrar por período
     */
    public function scopePorPeriodo($query, $inicio, $fin)
    {
        return $query->where('periodo_inicio', $inicio)
                     ->where('periodo_fin', $fin);
    }

    /**
     * Scope para planillas activas (no canceladas)
     */
    public function scopeActiva($query)
    {
        return $query->where('estado_planilla', '!=', 'cancelada');
    }

    /**
     * Scope para filtrar por ámbito (proyecto y/o departamento)
     */
    public function scopePorAmbito($query, ?int $proyectoId, ?int $departamentoId)
    {
        return $query->where(function ($q) use ($proyectoId) {
            if ($proyectoId) {
                $q->where('proyecto_id', $proyectoId);
            } else {
                $q->whereNull('proyecto_id');
            }
        })->where(function ($q) use ($departamentoId) {
            if ($departamentoId) {
                $q->where('departamento_id', $departamentoId);
            } else {
                $q->whereNull('departamento_id');
            }
        });
    }

    /**
     * Scope para verificar traslape de fechas
     * Encuentra planillas que se solapan con el rango dado
     */
    public function scopeConTraslapeFechas($query, $inicio, $fin)
    {
        return $query->where(function ($q) use ($inicio, $fin) {
            // Hay traslape si: inicio_existente <= fin_nuevo AND fin_existente >= inicio_nuevo
            $q->where('periodo_inicio', '<=', $fin)
              ->where('periodo_fin', '>=', $inicio);
        });
    }
    
    /**
     * Accessor para etiqueta de estado
     */
    public function getEstadoLabelAttribute(): string
    {
        $labels = [
            'borrador' => 'Borrador',
            'revision' => 'En Revisión',
            'aprobada' => 'Aprobada',
            'pagada' => 'Pagada',
            'cancelada' => 'Cancelada',
        ];
        
        return $labels[$this->estado_planilla] ?? $this->estado_planilla;
    }
    
    /**
     * Verificar si la planilla puede ser editada
     */
    public function puedeEditarse(): bool
    {
        return in_array($this->estado_planilla, ['borrador', 'revision']);
    }
    
    /**
     * Verificar si la planilla puede ser aprobada
     */
    public function puedeAprobarse(): bool
    {
        return $this->estado_planilla === 'borrador';
    }
}
