<?php

namespace App\Policies;

use App\Models\Nota;
use App\Models\User;

class NotaPolicy
{
    public function view(User $usuario, Nota $nota): bool
    {
        return $nota->usuario_id === $usuario->id;
    }

    public function update(User $usuario, Nota $nota): bool
    {
        return $nota->usuario_id === $usuario->id;
    }

    public function delete(User $usuario, Nota $nota): bool
    {
        return $nota->usuario_id === $usuario->id && ! $nota->trashed();
    }

    public function restore(User $usuario, Nota $nota): bool
    {
        return $nota->usuario_id === $usuario->id && $nota->trashed();
    }

    public function forceDelete(User $usuario, Nota $nota): bool
    {
        return $nota->usuario_id === $usuario->id && $nota->trashed();
    }
}
