<?php

namespace App\Models;

use App\Models\Catalogos\Departamento;
use App\Models\Catalogos\EstadoCivil;
use App\Models\Catalogos\Sexo;
use App\Models\Catalogos\TipoContratacion;
use App\Models\Catalogos\TipoPago;
use App\Models\Catalogos\TipoSangre;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Personal extends Model
{
    use SoftDeletes;

    protected $table = 'personal';

    protected $fillable = [
        'nombres',
        'apellidos',
        'dpi',
        'nit',
        'email',
        'telefono',
        'numero_igss',
        'fecha_nacimiento',
        'estado_civil_id',
        'altura',
        'tipo_sangre_id',
        'peso',
        'sabe_leer',
        'sabe_escribir',
        'es_alergico',
        'alergias',
        'tipo_contratacion_id',
        'salario_base',
        'tipo_pago_id',
        'puesto',
        'sexo_id',
        'departamento_id',
        'observaciones',
        'foto_perfil',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'altura' => 'decimal:2',
            'peso' => 'decimal:2',
            'salario_base' => 'decimal:2',
            'sabe_leer' => 'boolean',
            'sabe_escribir' => 'boolean',
            'es_alergico' => 'boolean',
        ];
    }

    protected $appends = ['nombre_completo', 'edad', 'iniciales', 'foto_url'];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->nombres} {$this->apellidos}",
        );
    }

    protected function edad(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fecha_nacimiento
                ? Carbon::parse($this->fecha_nacimiento)->age
                : null,
        );
    }

    protected function iniciales(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper(
                substr($this->nombres, 0, 1) . substr($this->apellidos, 0, 1)
            ),
        );
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->foto_perfil
                ? asset('storage/personal_fotos/' . $this->foto_perfil)
                : null,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships - Catalogs
    |--------------------------------------------------------------------------
    */

    public function estadoCivil(): BelongsTo
    {
        return $this->belongsTo(EstadoCivil::class, 'estado_civil_id');
    }

    public function tipoSangre(): BelongsTo
    {
        return $this->belongsTo(TipoSangre::class, 'tipo_sangre_id');
    }

    public function sexo(): BelongsTo
    {
        return $this->belongsTo(Sexo::class, 'sexo_id');
    }

    public function tipoContratacion(): BelongsTo
    {
        return $this->belongsTo(TipoContratacion::class, 'tipo_contratacion_id');
    }

    public function tipoPago(): BelongsTo
    {
        return $this->belongsTo(TipoPago::class, 'tipo_pago_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships - Related Tables
    |--------------------------------------------------------------------------
    */

    public function direccion(): HasOne
    {
        return $this->hasOne(PersonalDireccion::class, 'personal_id');
    }

    public function referenciasLaborales(): HasMany
    {
        return $this->hasMany(PersonalReferenciaLaboral::class, 'personal_id');
    }

    public function redesSociales(): HasMany
    {
        return $this->hasMany(PersonalRedSocial::class, 'personal_id');
    }

    public function familiares(): HasMany
    {
        return $this->hasMany(PersonalFamiliar::class, 'personal_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PersonalDocumento::class, 'personal_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(OperacionPersonalAsignado::class, 'personal_id');
    }

    public function asignacionesActivas(): HasMany
    {
        return $this->hasMany(OperacionPersonalAsignado::class, 'personal_id')
            ->where('estado_asignacion', 'activa');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeInactivos($query)
    {
        return $query->where('estado', 'inactivo');
    }

    public function scopeSuspendidos($query)
    {
        return $query->where('estado', 'suspendido');
    }

    public function scopeBuscar($query, ?string $termino)
    {
        if (!$termino) {
            return $query;
        }

        return $query->where(function ($q) use ($termino) {
            $q->where('nombres', 'ilike', "%{$termino}%")
              ->orWhere('apellidos', 'ilike', "%{$termino}%")
              ->orWhere('dpi', 'ilike', "%{$termino}%")
              ->orWhere('email', 'ilike', "%{$termino}%")
              ->orWhere('telefono', 'ilike', "%{$termino}%")
              ->orWhere('puesto', 'ilike', "%{$termino}%");
        });
    }

    public function scopeByDepartamento($query, ?int $departamentoId)
    {
        if (!$departamentoId) {
            return $query;
        }

        return $query->where('departamento_id', $departamentoId);
    }

    public function scopeByEstado($query, ?string $estado)
    {
        if (!$estado) {
            return $query;
        }

        return $query->where('estado', $estado);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function getContactoEmergencia()
    {
        return $this->familiares()
            ->where('es_contacto_emergencia', true)
            ->first();
    }

    public function getDocumentosVencidos()
    {
        return $this->documentos()
            ->where('estado_documento', 'vencido')
            ->get();
    }

    public function getDocumentosPorVencer()
    {
        return $this->documentos()
            ->where('estado_documento', 'por_vencer')
            ->get();
    }
}
