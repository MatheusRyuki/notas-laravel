<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class BuscaNotas
{
    public static function aplicar(Builder|Relation $consulta, ?string $termo): Builder|Relation
    {
        if ($termo === null) {
            return $consulta;
        }

        $padrao = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termo).'%';

        return $consulta->where(function (Builder $trecho) use ($padrao): void {
            $trecho
                ->whereRaw("titulo LIKE ? ESCAPE '!'", [$padrao])
                ->orWhereRaw("descricao LIKE ? ESCAPE '!'", [$padrao]);
        });
    }
}
