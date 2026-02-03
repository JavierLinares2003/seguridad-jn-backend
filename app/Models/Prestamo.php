<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'operaciones_prestamos';

    protected $fillable = [
        'personal_id',
        'monto_total',
        'saldo_pendiente',
        'tasa_interes',
        'fecha_prestamo',
        'fecha_primer_pago',
        'cuotas_totales',
        'cuotas_pagadas',
        'monto_cuota',
        'estado_prestamo',
        'observaciones',
        'aprobado_por_user_id',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'tasa_interes' => 'decimal:2',
        'fecha_prestamo' => 'date',
        'fecha_primer_pago' => 'date',
        'cuotas_totales' => 'integer',
        'cuotas_pagadas' => 'integer',
        'monto_cuota' => 'decimal:2',
    ];

    // Relationships
    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }

    public function transacciones()
    {
        return $this->hasMany(Transaccion::class, 'prestamo_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado_prestamo', 'activo');
    }

    public function scopePorPersonal($query, $personalId)
    {
        return $query->where('personal_id', $personalId);
    }

    // Accessors
    public function getEstadoLabelAttribute()
    {
        $labels = [
            'activo' => 'Activo',
            'pagado' => 'Pagado',
            'cancelado' => 'Cancelado',
        ];

        return $labels[$this->estado_prestamo] ?? $this->estado_prestamo;
    }

    public function getPorcentajePagadoAttribute()
    {
        if ($this->monto_total == 0) return 0;
        
        $montoPagado = $this->monto_total - $this->saldo_pendiente;
        return round(($montoPagado / $this->monto_total) * 100, 2);
    }
}
