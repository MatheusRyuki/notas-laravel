<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nome'])]
class Etiqueta extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Etiqueta $etiqueta): void {
            $etiqueta->nome = trim(preg_replace('/\s+/u', ' ', $etiqueta->nome) ?? '');
            $etiqueta->nome_normalizado = mb_strtolower($etiqueta->nome);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsToMany<Nota, $this> */
    public function notas(): BelongsToMany
    {
        return $this->belongsToMany(Nota::class, 'etiqueta_nota')
            ->withPivot('usuario_id')
            ->withTimestamps();
    }
}
