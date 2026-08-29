<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaFacturaCompra extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_facturas_compra';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_facturas_compra';

    protected $fillable = [
        'proveedor_id',
        'fecha_factura',
        'serie',
        'numero_factura',
        'total',
        'observaciones',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha_factura' => 'date',
        'total' => 'decimal:2',
    ];

    protected $appends = ['documento'];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(BodegaProveedor::class, 'proveedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BodegaFacturaCompraItem::class, 'factura_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BodegaMovimiento::class, 'factura_compra_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function getDocumentoAttribute(): string
    {
        $serie = trim((string) $this->serie);
        $numero = trim((string) $this->numero_factura);

        return $serie !== '' ? $serie . '-' . $numero : $numero;
    }
}
