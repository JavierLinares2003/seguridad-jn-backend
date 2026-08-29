<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaEntregaItem extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_entrega_items';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_entrega_items';

    protected $fillable = [
        'entrega_id',
        'variante_id',
        'cantidad',
        'cantidad_devuelta',
        'precio_unitario',
        'subtotal',
        'movimiento_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_devuelta' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected $appends = ['cantidad_pendiente'];

    public function getCantidadPendienteAttribute(): int
    {
        return max(0, (int) $this->cantidad - (int) $this->cantidad_devuelta);
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(BodegaEntrega::class, 'entrega_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(BodegaVariante::class, 'variante_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(BodegaMovimiento::class, 'movimiento_id');
    }
}
