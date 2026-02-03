<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectoContacto extends Model
{
    use HasFactory;

    protected $table = 'proyectos_contactos';

    protected $fillable = [
        'proyecto_id',
        'nombre_contacto',
        'telefono',
        'email',
        'puesto',
        'es_contacto_principal',
    ];

    protected $casts = [
        'es_contacto_principal' => 'boolean',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }
}
