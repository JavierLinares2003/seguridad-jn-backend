<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    use HasFactory, AuditableModel;

    protected string $logName = 'transacciones';
    protected string $modulo = 'operaciones';

    protected $table = 'operaciones_transacciones';

    protected $fillable = [
        'personal_id',
        'asistencia_id',
        'tipo_transaccion',
        'monto',
        'descripcion',
        'fecha_transaccion',
        'es_descuento',
        'estado_transaccion',
        'prestamo_id',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_transaccion' => 'date',
        'es_descuento' => 'boolean',
    ];

    // Relationships
    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function asistencia()
    {
        return $this->belongsTo(OperacionAsistencia::class, 'asistencia_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    // Scopes
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_transaccion', $tipo);
    }

    public function scopePorPersonal($query, $personalId)
    {
        return $query->where('personal_id', $personalId);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_transaccion', 'pendiente');
    }

    // Accessors
    public function getTipoLabelAttribute()
    {
        $labels = [
            'multa' => 'Multa',
            'uniforme' => 'Uniforme',
            'anticipo' => 'Anticipo',
            'prestamo' => 'Préstamo',
            'abono_prestamo' => 'Abono a Préstamo',
            'antecedentes' => 'Antecedentes',
            'otro_descuento' => 'Otro Descuento',
        ];

        return $labels[$this->tipo_transaccion] ?? $this->tipo_transaccion;
    }

    public function getEstadoLabelAttribute()
    {
        $labels = [
            'pendiente' => 'Pendiente',
            'aplicado' => 'Aplicado',
            'cancelado' => 'Cancelado',
        ];

        return $labels[$this->estado_transaccion] ?? $this->estado_transaccion;
    }
}
