<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property float $monto_total
 * @property float $saldo_pendiente
 * @property float|null $tasa_interes
 * @property-read float $monto_interes
 * @property-read float $monto_con_interes
 * @property-read float $porcentaje_pagado
 */
class Prestamo extends Model
{
    use HasFactory, AuditableModel;

    protected string $logName = 'prestamos';
    protected string $modulo = 'operaciones';

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
        'comprobante_ruta',
        'comprobante_nombre_original',
        'comprobante_extension',
        'comprobante_tamanio_kb',
        'comprobante_subido_por_user_id',
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

    protected $appends = [
        'monto_interes',
        'monto_con_interes',
        'porcentaje_pagado',
    ];

    public static function totalConInteres(float $capital, float $tasa = 0): float
    {
        return \App\Services\PrestamoService::calcularTotalConInteres($capital, $tasa);
    }

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

    public function getMontoInteresAttribute(): float
    {
        return round((float) $this->monto_total * ((float) ($this->tasa_interes ?? 0) / 100), 2);
    }

    public function getMontoConInteresAttribute(): float
    {
        return self::totalConInteres((float) $this->monto_total, (float) ($this->tasa_interes ?? 0));
    }

    public function getPorcentajePagadoAttribute()
    {
        $total = $this->monto_con_interes;
        if ($total == 0) {
            return 0;
        }

        $montoPagado = $total - (float) $this->saldo_pendiente;

        return max(0, min(100, round(($montoPagado / $total) * 100, 2)));
    }
}
