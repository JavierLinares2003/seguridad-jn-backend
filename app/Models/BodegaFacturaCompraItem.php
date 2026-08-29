<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaFacturaCompraItem extends Model
{
    protected $table = 'bodega_factura_compra_items';

    protected $fillable = [
        'factura_id',
        'variante_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'movimiento_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(BodegaFacturaCompra::class, 'factura_id');
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
