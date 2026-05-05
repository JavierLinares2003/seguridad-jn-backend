<?php

namespace App\Models;

use App\Models\Catalogos\Departamento;
use App\Models\Catalogos\EstadoCivil;
use App\Models\Catalogos\NivelEstudio;
use App\Models\Catalogos\Sexo;
use App\Models\Catalogos\TipoContratacion;
use App\Models\Catalogos\TipoPago;
use App\Models\Catalogos\TipoSangre;
use App\Traits\AuditableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Personal extends Model
{
    use SoftDeletes, AuditableModel;

    protected string $logName = 'personal';
    protected string $modulo = 'personal';

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
        'sabe_usar_computadora',
        'es_alergico',
        'alergias',
        'tipo_contratacion_id',
        'salario_base',
        'tiene_igss',
        'tiene_prestaciones',
        'tiene_bono14',
        'tipo_pago_id',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'nombre_cuenta',
        'puesto',
        'sexo_id',
        'nivel_estudio_id',
        'departamento_id',
        'fecha_inicio',
        'observaciones',
        'vacaciones_saldo_inicial',
        'foto_perfil',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_inicio' => 'date',
            'altura' => 'decimal:2',
            'peso' => 'decimal:2',
            'salario_base' => 'decimal:2',
            'sabe_leer' => 'boolean',
            'sabe_escribir' => 'boolean',
            'sabe_usar_computadora' => 'boolean',
            'es_alergico' => 'boolean',
            'tiene_igss' => 'boolean',
            'tiene_prestaciones' => 'boolean',
            'tiene_bono14' => 'boolean',
            'vacaciones_saldo_inicial' => 'integer',
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

    public function nivelEstudio(): BelongsTo
    {
        return $this->belongsTo(NivelEstudio::class, 'nivel_estudio_id');
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

    public function historialSalarios(): HasMany
    {
        return $this->hasMany(PersonalHistorialSalario::class, 'personal_id');
    }

    public function vacaciones(): HasMany
    {
        return $this->hasMany(PersonalVacacion::class, 'personal_id');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(PersonalPermiso::class, 'personal_id');
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

        $tokens = array_values(array_filter(explode(' ', trim($termino))));

        foreach ($tokens as $token) {
            $like = '%' . $token . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("unaccent(nombres) ilike unaccent(?)", [$like])
                  ->orWhereRaw("unaccent(apellidos) ilike unaccent(?)", [$like])
                  ->orWhereRaw("unaccent(puesto) ilike unaccent(?)", [$like])
                  ->orWhere('dpi', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('telefono', 'like', $like);
            });
        }

        return $query;
    }

    public function scopeByDepartamento($query, ?int $departamentoId)
    {
        if (!$departamentoId) {
            return $query;
        }

        return $query->where('departamento_id', $departamentoId);
    }

    public function scopeByDepartamentoNombre($query, ?string $nombre)
    {
        if (!$nombre) {
            return $query;
        }

        return $query->whereHas('departamento', fn ($q) => $q->where('nombre', 'ilike', $nombre));
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
