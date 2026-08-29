<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaCompra extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_compras';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_compras';

    public const ESTADOS = [
        'solicitud',
        'cotizacion',
        'aprobada',
        'anticipo_pagado',
        'recibida',
        'saldo_pagado',
        'cancelada',
    ];

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'estado',
        'fecha_solicitud',
        'fecha_cotizacion',
        'fecha_aprobacion',
        'fecha_anticipo_pagado',
        'fecha_recepcion',
        'fecha_saldo_pagado',
        'total_estimado',
        'total_final',
        'anticipo_porcentaje',
        'anticipo_pagado',
        'saldo_pagado',
        'observaciones',
        'registrado_por_user_id',
        'aprobado_por_user_id',
        'factura_compra_id',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_cotizacion' => 'date',
        'fecha_aprobacion' => 'date',
        'fecha_anticipo_pagado' => 'date',
        'fecha_recepcion' => 'date',
        'fecha_saldo_pagado' => 'date',
        'total_estimado' => 'decimal:2',
        'total_final' => 'decimal:2',
        'anticipo_pagado' => 'boolean',
        'saldo_pagado' => 'boolean',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(BodegaProveedor::class, 'proveedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BodegaCompraItem::class, 'compra_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(BodegaFacturaCompra::class, 'factura_compra_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }
}
