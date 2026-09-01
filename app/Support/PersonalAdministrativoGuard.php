<?php

namespace App\Support;

use App\Models\Personal;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class PersonalAdministrativoGuard
{
    public static function usuario(?Authenticatable $user): ?User
    {
        return $user instanceof User ? $user : null;
    }

    public static function tiene(?Authenticatable $user, string $permiso): bool
    {
        return self::usuario($user)?->can($permiso) ?? false;
    }

    public static function puedeVerExpediente(?Authenticatable $user, Personal $personal): bool
    {
        if (!$personal->es_administrativo) {
            return true;
        }

        return self::tiene($user, 'view-personal-administrativo');
    }

    public static function puedeVerNomina(?Authenticatable $user, Personal $personal): bool
    {
        if (!$personal->es_administrativo) {
            return true;
        }

        if (self::tiene($user, 'view-personal-administrativo')) {
            return true;
        }

        return self::tiene($user, 'view-planillas')
            && self::tiene($user, 'view-personal-sensible')
            && !self::tiene($user, 'manage-asistencia');
    }

    public static function puedeEditar(?Authenticatable $user, Personal $personal): bool
    {
        if (!$personal->es_administrativo) {
            return self::tiene($user, 'edit-personal');
        }

        return self::tiene($user, 'manage-personal-administrativo');
    }

    public static function abortSiNoPuedeVerExpediente(?Authenticatable $user, Personal $personal): void
    {
        if (!self::puedeVerExpediente($user, $personal)) {
            abort(403, 'El expediente de personal administrativo solo lo ven gerencia y recursos humanos.');
        }
    }

    public static function abortSiNoPuedeEditar(?Authenticatable $user, Personal $personal): void
    {
        if (!self::puedeEditar($user, $personal)) {
            abort(403, 'No puede editar expedientes de personal administrativo.');
        }
    }
}
