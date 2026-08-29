<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaCategoria extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_categorias';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_categorias';

    protected $fillable = [
        'nombre',
        'codigo',
        'prefijo_correlativo',
        'icono',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(BodegaProducto::class, 'categoria_id');
    }
}
