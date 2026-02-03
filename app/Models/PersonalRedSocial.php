<?php

namespace App\Models;

use App\Models\Catalogos\RedSocial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRedSocial extends Model
{
    protected $table = 'personal_redes_sociales';

    protected $fillable = [
        'personal_id',
        'red_social_id',
        'nombre_usuario',
        'url_perfil',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function redSocial(): BelongsTo
    {
        return $this->belongsTo(RedSocial::class, 'red_social_id');
    }
}
