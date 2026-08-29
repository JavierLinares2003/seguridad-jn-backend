<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaMovimiento extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_movimientos';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_movimientos';

    protected $fillable = [
        'variante_id',
        'tipo',
        'cantidad',
        'existencia_anterior',
        'existencia_nueva',
        'fecha_movimiento',
        'personal_id',
        'proyecto_id',
        'proveedor_id',
        'registrado_por_user_id',
        'referencia',
        'observaciones',
        'entrega_id',
        'factura_compra_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'existencia_anterior' => 'integer',
        'existencia_nueva' => 'integer',
        'fecha_movimiento' => 'date',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(BodegaVariante::class, 'variante_id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(BodegaProveedor::class, 'proveedor_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(BodegaEntrega::class, 'entrega_id');
    }

    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(BodegaFacturaCompra::class, 'factura_compra_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'ingreso' => 'Ingreso',
            'egreso' => 'Egreso',
            'ajuste' => 'Ajuste',
            'ajuste_inicial' => 'Inventario inicial',
            default => $this->tipo,
        };
    }
}
