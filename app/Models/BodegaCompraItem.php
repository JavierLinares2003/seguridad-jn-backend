<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaCompraItem extends Model
{
    protected $table = 'bodega_compra_items';

    protected $fillable = [
        'compra_id',
        'descripcion',
        'cantidad',
        'precio_estimado',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_estimado' => 'decimal:2',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(BodegaCompra::class, 'compra_id');
    }
}
