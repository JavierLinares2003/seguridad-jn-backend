<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodegaEntrega extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_entregas';
    protected string $modulo = 'bodega';

    protected $table = 'bodega_entregas';

    protected $fillable = [
        'numero_boleta',
        'personal_id',
        'modo_entrega',
        'personal_operaciones_id',
        'tipo',
        'cobrar',
        'monto_total',
        'motivo_reposicion',
        'cambio_por_dano',
        'variante_entrada_dano_id',
        'cantidad_entrada_dano',
        'observaciones',
        'fecha_entrega',
        'devuelta_at',
        'registrado_por_user_id',
        'grupo_uniforme',
    ];

    protected $casts = [
        'cobrar' => 'boolean',
        'cambio_por_dano' => 'boolean',
        'monto_total' => 'decimal:2',
        'fecha_entrega' => 'date',
        'devuelta_at' => 'datetime',
    ];

    protected $appends = ['pendiente_devolucion'];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function personalOperaciones(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_operaciones_id');
    }

    public function varianteEntradaDano(): BelongsTo
    {
        return $this->belongsTo(BodegaVariante::class, 'variante_entrada_dano_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BodegaEntregaItem::class, 'entrega_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BodegaMovimiento::class, 'entrega_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'kit' => 'Kit / uniforme',
            'reposicion' => 'Reposición',
            'simple' => 'Entrega simple',
            'cambio_dano' => 'Cambio por daño',
            default => $this->tipo,
        };
    }

    public function getPendienteDevolucionAttribute(): bool
    {
        return $this->devuelta_at === null;
    }
}
