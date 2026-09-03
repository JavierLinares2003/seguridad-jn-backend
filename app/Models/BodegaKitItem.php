<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaKitItem extends Model
{
    protected $table = 'bodega_kit_items';

    protected $fillable = [
        'kit_id',
        'producto_id',
        'cantidad',
        'precio',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio' => 'decimal:2',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(BodegaKit::class, 'kit_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(BodegaProducto::class, 'producto_id');
    }
}
