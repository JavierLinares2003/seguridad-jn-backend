<?php

namespace App\Models;

use App\Models\Catalogos\TipoDocumentoPersonal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalDocumento extends Model
{
    use SoftDeletes;

    protected $table = 'personal_documentos';

    protected $fillable = [
        'personal_id',
        'tipo_documento_personal_id',
        'nombre_documento',
        'descripcion',
        'ruta_archivo',
        'nombre_archivo_original',
        'extension',
        'tamanio_kb',
        'fecha_vencimiento',
        'fecha_subida',
        'subido_por_user_id',
        'estado_documento',
        'dias_alerta_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'fecha_subida' => 'datetime',
            'tamanio_kb' => 'integer',
            'dias_alerta_vencimiento' => 'integer',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumentoPersonal::class, 'tipo_documento_personal_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVigentes($query)
    {
        return $query->where('estado_documento', 'vigente');
    }

    public function scopePorVencer($query)
    {
        return $query->where('estado_documento', 'por_vencer');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado_documento', 'vencido');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function actualizarEstado(): void
    {
        if (!$this->fecha_vencimiento) {
            $this->estado_documento = 'vigente';
            $this->save();
            return;
        }

        $hoy = Carbon::today();
        $vencimiento = Carbon::parse($this->fecha_vencimiento);
        $diasParaVencer = $hoy->diffInDays($vencimiento, false);

        if ($diasParaVencer < 0) {
            $this->estado_documento = 'vencido';
        } elseif ($diasParaVencer <= $this->dias_alerta_vencimiento) {
            $this->estado_documento = 'por_vencer';
        } else {
            $this->estado_documento = 'vigente';
        }

        $this->save();
    }

    public function getTamanioFormateado(): string
    {
        if ($this->tamanio_kb >= 1024) {
            return number_format($this->tamanio_kb / 1024, 2) . ' MB';
        }

        return $this->tamanio_kb . ' KB';
    }
}
