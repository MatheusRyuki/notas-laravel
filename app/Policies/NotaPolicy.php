<?php

namespace App\Policies;

use App\Enums\PapelNota;
use App\Models\Nota;
use App\Models\User;

class NotaPolicy
{
    public function view(User $usuario, Nota $nota): bool
    {
        if ($nota->pertenceA($usuario)) {
            return true;
        }

        return ! $nota->trashed() && $nota->papelDe($usuario) !== null;
    }

    public function update(User $usuario, Nota $nota): bool
    {
        return ! $nota->trashed()
            && ($nota->pertenceA($usuario) || $nota->papelDe($usuario) === PapelNota::Editor);
    }

    public function manageState(User $usuario, Nota $nota): bool
    {
        return $nota->pertenceA($usuario) && ! $nota->trashed();
    }

    public function share(User $usuario, Nota $nota): bool
    {
        return $nota->pertenceA($usuario) && ! $nota->trashed();
    }

    public function delete(User $usuario, Nota $nota): bool
    {
        return $this->manageState($usuario, $nota);
    }

    public function restore(User $usuario, Nota $nota): bool
    {
        return $nota->pertenceA($usuario) && $nota->trashed();
    }

    public function forceDelete(User $usuario, Nota $nota): bool
    {
        return $nota->pertenceA($usuario) && $nota->trashed();
    }
}
