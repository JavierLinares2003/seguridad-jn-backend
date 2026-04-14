<?php

namespace App\Observers;

use App\Models\Personal;
use App\Models\PersonalHistorialSalario;
use Illuminate\Support\Facades\Auth;

class PersonalObserver
{
    public function updating(Personal $personal): void
    {
        if (! $personal->isDirty('salario_base')) {
            return;
        }

        $salarioAnterior = $personal->getOriginal('salario_base');
        $salarioNuevo    = $personal->salario_base;

        if ((float) $salarioAnterior === (float) $salarioNuevo) {
            return;
        }

        PersonalHistorialSalario::create([
            'personal_id'     => $personal->id,
            'salario_anterior' => $salarioAnterior,
            'salario_nuevo'    => $salarioNuevo,
            'fecha_cambio'     => now()->toDateString(),
            'cambiado_por'     => Auth::id(),
            'motivo'           => null,
        ]);
    }
}
