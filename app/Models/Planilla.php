<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
