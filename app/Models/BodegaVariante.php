<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaVariante extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_variantes';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_variantes';

    protected $fillable = [
        'producto_id',
        'talla',
        'condicion',
        'genero',
        'sku',
        'existencia',
        'stock_minimo',
        'activo',
    ];

    protected $casts = [
        'existencia' => 'integer',
        'stock_minimo' => 'integer',
        'activo' => 'boolean',
    ];

    protected $appends = ['etiqueta', 'stock_bajo', 'precio_sugerido'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(BodegaProducto::class, 'producto_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BodegaMovimiento::class, 'variante_id');
    }

    public function getEtiquetaAttribute(): string
    {
        $parts = [];
        if ($this->genero) {
            $parts[] = ucfirst($this->genero);
        }
        if ($this->talla) {
            $parts[] = 'Talla ' . $this->talla;
        }
        if ($this->condicion) {
            $parts[] = ucfirst($this->condicion);
        }

        return $parts ? implode(' · ', $parts) : 'Única';
    }

    public function getStockBajoAttribute(): bool
    {
        return $this->existencia <= $this->stock_minimo;
    }

    public function getPrecioSugeridoAttribute(): float
    {
        $producto = $this->relationLoaded('producto') ? $this->producto : $this->producto()->first();
        if (!$producto) {
            return 0.0;
        }

        return $producto->precioParaCondicion($this->condicion);
    }
}
