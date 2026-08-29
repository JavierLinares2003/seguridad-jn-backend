<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaProducto extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_productos';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_productos';

    protected $fillable = [
        'categoria_id',
        'codigo',
        'nombre',
        'unidad',
        'precio_venta',
        'precio_usado',
        'usa_talla',
        'usa_condicion',
        'usa_genero',
        'es_uniforme',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'precio_usado' => 'decimal:2',
        'usa_talla' => 'boolean',
        'usa_condicion' => 'boolean',
        'usa_genero' => 'boolean',
        'es_uniforme' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $appends = ['existencia_total'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(BodegaCategoria::class, 'categoria_id');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(BodegaVariante::class, 'producto_id');
    }

    public function kits(): HasMany
    {
        return $this->hasMany(BodegaKitItem::class, 'producto_id');
    }

    public function getExistenciaTotalAttribute(): int
    {
        if ($this->relationLoaded('variantes')) {
            return (int) $this->variantes->sum('existencia');
        }

        return (int) $this->variantes()->sum('existencia');
    }

    public function precioParaCondicion(?string $condicion): float
    {
        if ($condicion === 'usado') {
            if ($this->precio_usado !== null) {
                return round((float) $this->precio_usado, 2);
            }
            $base = (float) ($this->precio_venta ?? 0);
            if ($this->es_uniforme) {
                return round($base / 2, 2);
            }

            return round($base, 2);
        }

        return round((float) ($this->precio_venta ?? 0), 2);
    }

    public function attachProductoToVariantes(): static
    {
        if ($this->relationLoaded('variantes')) {
            $this->variantes->each(fn (BodegaVariante $v) => $v->setRelation('producto', $this));
        }

        return $this;
    }
}
