<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaProveedor extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_proveedores';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_proveedores';

    protected $fillable = [
        'codigo',
        'nombre',
        'insumo',
        'telefono',
        'numero_cuenta',
        'banco',
        'contacto',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(BodegaMovimiento::class, 'proveedor_id');
    }

    public function facturasCompra(): HasMany
    {
        return $this->hasMany(BodegaFacturaCompra::class, 'proveedor_id');
    }
}
