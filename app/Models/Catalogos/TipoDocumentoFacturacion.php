<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumentoFacturacion extends Model
{
    use HasFactory;

    protected $table = 'tipos_documentos_facturacion';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
