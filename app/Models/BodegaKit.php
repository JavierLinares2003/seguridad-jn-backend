<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaKit extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_kits';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_kits';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BodegaKitItem::class, 'kit_id');
    }
}
